<?php

class UserIdentity extends CUserIdentity
{
    
    public function authenticate()
    {
        $model = User::model()->findByAttributes(array('login'=>$this->username));

		/**
		 * @var User $model
		 */
        
        if($model and $model->pass===User::hash($this->password) and $model->active)
 		{
 			//дополнительная проверка для админа
 			if($model->role==User::ROLE_ADMIN)
			{
				if(cfg('admin_ip_filter'))
				{
					if(!Tools::isAdminIp())
					{
						$this->errorMessage = 'wrong ip';
						return false;
					}
				}
			}

 			$this->setState('id', $model->id);
            $this->setState('login', $model->login);
            $this->setState('role', $model->role);
            $this->setState('name', $model->name);
            $this->setState('pass', $model->pass);

            return true;
 		}
 		elseif($model and !$model->active)
 			$this->errorMessage = 'Неверный логин или пароль';
 		else
 			$this->errorMessage = 'Неверный логин или пароль';
    }
}