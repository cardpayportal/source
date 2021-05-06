<?php

class NoticeAdmin extends CApplicationComponent {

    public $url;    //апи-ссылка
    public $key;    //ключ доступа для уведомлений
    public $interval;   //пауза между уведомлениями
    public $dataFile;   //файл для сохранения даты последнего уведомления
    public $error;  //последняя возникшая ошибка

    private $textLength = 200;  //максимальная длина текста сообщения

    public function send($text)
    {
        $lastNoticeTime = $this->data('lastNoticeTime')*1;

        if($this->error)
            return false;

        if(time() - $lastNoticeTime > $this->interval)
        {

            if(mb_strlen($text, 'utf-8') > $this->textLength)
                $text = mb_substr($text, 0, $this->textLength - 2, 'utf-8').'..';

            $url = str_replace(array('{key}', '{text}'), array($this->key, rawurlencode($text)), $this->url);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

            $content = curl_exec($ch);
            curl_close($ch);

            if($json = json_decode($content, true))
            {
                if($json['result']==true)
                {
                    return $this->data('lastNoticeTime', time());
                }
                else
                {
                    $this->error = $json['error'];
                    return false;
                }
            }
            else
            {
                $this->error = 'неверный ответ от сервера: '.$content;
                return false;
            }
        }

        return true;
    }

    /*
     * array(
     *  'lastNoticeTime'=>'',
     * )
     */
    private function data($key, $val=null)
    {
        if(!$key)
        {
            $this->error = 'не указан $key в data()';
            return false;
        }

        if(!file_exists($this->dataFile) or !file_get_contents($this->dataFile))
            file_put_contents($this->dataFile, json_encode(array()));

        if(!is_writable($this->dataFile))
        {
            $this->error = 'файл '.$this->dataFile.' не существует или не доступен для записи';
            return false;
        }

        $json = json_decode(file_get_contents($this->dataFile), true);

        if($val===null)
        {
            //чтение
            return $json[$key];
        }
        else
        {
            //запись
            $json[$key] = $val;

            $result = file_put_contents($this->dataFile, json_encode($json));

            if($result === false)
            {
                $this->error = 'ошибка записи файла: '.$this->dataFile;
                return false;
            }
            else
                return true;
        }
    }
}
