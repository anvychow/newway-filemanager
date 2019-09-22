<?php 

define( 'ABSPATH', dirname(dirname(__FILE__)) . '/' );

abstract class AccessLevel {
	const NoAccess = -1;
	const ReadOnly = 0;
	const ReadWrite = 1;
	const ReadWriteDelete = 2;
	const Admin = 3;
}


class User {

	public function __construct(string $email, string $password, int $access_level) {

		$this->access_level = $access_level;
		$this->email = $email;
		$this->password = $password;

	}

	public function canReadFiles() {

		return ($this->access_level == AccessLevel::ReadOnly ||
				$this->access_level == AccessLevel::ReadWrite ||
				$this->access_level == AccessLevel::ReadWriteDelete ||
				$this->access_level == AccessLevel::Admin);

	}

	public function canWriteFiles() {

		return ($this->access_level == AccessLevel::ReadWrite ||
				$this->access_level == AccessLevel::ReadWriteDelete ||
				$this->access_level == AccessLevel::Admin);
	}

	public function canDeleteFiles() {

		return ($this->access_level == AccessLevel::ReadWriteDelete ||
				$this->access_level == AccessLevel::Admin);
	}

	public function canAddUsers() {
		return $this->access_level == AccessLevel::Admin;
	}
	
}

// a singleton to get the instance of the currently
// logged in user
class SessionUser {
	static $current_user_instance = null;
	// returns current logged in user instance from session
	public function getCurrenUserInstance($json_file_name="") {

		if (self::$current_user_instance == null) {
			if (isset($_SESSION['email'], $_SESSION['password'])) {
				self::$current_user_instance = JsonUserDataManager::getInstance($json_file_name)->getUser($_SESSION['email'], $_SESSION['password']);
			}
		}

		return self::$current_user_instance;

	}

}

interface UserDataManager {

	public function getUser(?string $email, ?string $password):?User;

	public function insertUser(User $user):bool;

	public function save():bool;

	public function checkIfAdminUserPresent():bool;
}



class JsonUserDataManager implements UserDataManager {

	static $user_data_manager_instance = null;

	public function getInstance($json_file_name=""):JsonUserDataManager {

		if (self::$user_data_manager_instance == null) {
		 	self::$user_data_manager_instance = new JsonUserDataManager($json_file_name);
		}
		return self::$user_data_manager_instance;
	}

	private function __construct($json_file_name) {
		$this->json_file_name = "newway_users.json";
		if ($json_file_name != "") {
			$this->json_file_name = $json_file_name;	
		}

		$this->full_file_path = ABSPATH.$this->json_file_name;
		$this->user_data = array();
		$this->loadFileContents();

	}

	private function loadFileContents() {
		// check if file is present
		if (file_exists($this->full_file_path)) {
			$file_pointer = fopen($this->full_file_path, "r");
			
			try {
				if ($file_pointer) {
					$this->user_data = json_decode(fread($file_pointer, 
						filesize($this->full_file_path)), true);

				}
				else {
					throw new Exception("Unable to create flat file database, please give correct
						permissions for newway to work properly");
				}
			}

			catch(Exception $e) {
				echo $e->getMessage();
			}
		}
	}

	public function getUser(?string $email, ?string $password):?User {

		if (array_key_exists($email, $this->user_data)) {

			$single_user_data = $this->user_data[$email];

			return new User($single_user_data['email'], $single_user_data['password'], $single_user_data['access_level']);
		}
		else {

			return null;
		}

	}
	

	public function insertUser(User $user):bool {

		// first check if there are any user present in current db
		// if there are not then it is first time installation
		if (count($this->user_data) > 0) {

			// then check if the user has the permission to 
			// add the new user
			$current_user_instance = SessionUser::getCurrenUserInstance();
			if ($current_user_instance == null) {
				// unauthorised login access, so return false
				return false;
			}
			else {
				// check for access level
				// and also check for duplicate email address
				if ($current_user_instance->canAddUsers() &&
					$current_user_instance->email != $user->email) {
					// has access
					return $this->constructArrayAndSaveToDb($user);
				}
				else {
					return false;
				}
			}

		}
		else {
			// new user first installation, simply register
			return $this->constructArrayAndSaveToDb($user);
		}
    	
    }

    private function constructArrayAndSaveToDb($user) {
    		// allow user to be registered
			$this->user_data[$user->email] = array(
													"email"=>$user->email,
													"password"=>$user->password,
													"access_level"=>$user->access_level
												);
			// and call save
    		return $this->save();
    }

    public function save():bool {
    	$file_contents = json_encode($this->user_data);
		$file_pointer = fopen($this->full_file_path, "w+");
		return fwrite($file_pointer, $file_contents) > 0;

    }

    public function checkIfAdminUserPresent():bool {

    	if (count($this->user_data) > 0) {
    		$users = $this->user_data;
    		foreach ($users as $user) {
    			if ($user['access_level'] == AccessLevel::Admin) {
    				return true;
    				break;
    			}
    		}
    		return false;
    	}
    	else {
    		return false;
    	}
    }


}



?>