<?php

class AdminAuth extends CUserIdentity
{
    
    public function authenticate()
    {
        $model = User::model()->findByAttributes(array('login'=>$this->username));
        
        if($model and $model->active)
 		{ 			
 			$this->setState('id', $model->id);
            $this->setState('login', $model->login);
            $this->setState('role', $model->role);
            $this->setState('name', $model->name);
            $this->setState('pass', $model->pass);
            return true;
 		}
 		elseif($model and !$model->active)
 			$this->errorMessage = 'Аккаунт не активирован';
 		else
 			$this->errorMessage = 'Неверный логин или пароль';
    }
}