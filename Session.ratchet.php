<?php 
//if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* Use memcached as session storage backend in CodeIgniter
*
* @author David Wischhusen <davewisch@gmail.com>
*/

/**
* Memcache Class
*/
class CI_Session{

	/**
	* Memcache Object
	*/
	public $memcache;

	/**
	* Session Name/Cookie Name
	*/
	public $sessionname;

	/**
	* Session expire time (seconds)
	*/
	public $session_length;

	/**
	* Memcache servers to connect to (array)
	*/
	public $servers;

	/**
	* Unique identifier, unique to each session
	*/
	public $session_id;

	/**
	* Class constructor
	*
	* @access public
	*/
	public function CI_Session(){
		//log_message('debug', "Session [memcache] Class Initialized");

		//load config
//		$CI = &get_instance();
		$this->sessionname ="PHPSESSID";
		$this->session_length = 36288000;
		$this->servers = array(
								array(
									'host' => '192.168.1.111',
									'port' => 11211
								)
							);;

		$this->session_id = false;

		$this->memcache = new Memcache;
		$this->connect();

		$this->init();
	}

//////////////CI Interface functions
/*
Originally this class wasn't meant to be a drop-in session replacement.
Eventually I'll convert it completely but for now I basically aliased
my functions to recreate the CodeIgniter session functions.
*/
	/**
	* Replicates the set_userdata function from CI Session library
	*
	* @access public
	* @param string
	* @param mixed
	*/
	public function set_userdata($data, $value=NULL){
		if(is_array($data)){
			foreach($data as $key=>$value){
				$this->set($key, $value);
			}
		}
		else if(is_string($data)){
			$this->set($data, $value);
		}
	}

	/**
	* Replicates the userdata function from the CI Session library
	*
	* @access public
	* @param string
	* @return mixed
	*/
	public function userdata($key){
		return $this->get($key);
	}

	/**
	* Replicates the unset_userdata function from the CI Session library
	*
	* @access public
	* @param string
	* @return bool
	*/
	public function unset_userdata($key){
		return $this->delete($key);
	}

	//set_flashdata is already defined below

	/**
	* Replicates the flashdata function from the CI Session library
	*
	* @access public
	* @param key
	* @return mixed
	*/
	public function flashdata($key){
		return $this->get_flashdata($key);
	}

	/**
	* Replicates the keep_flashdata function from the CI Session library
	*
	* @access public
	* @param string
	* @return bool
	*/
	public function keep_flashdata($key){
		return $this->extend_flashdata($key);
	}

	/**
	* Replicates the sess_destroy function from the CI Session library
	*/
	public function sess_destroy(){
		$this->destroy();
	}
//////////////////END CI interface functions

	/**
	* Start or resume session
	*
	* Looks to see if a valid session already exists in user cookie
	* and resumes session or starts a new one
	*
	* @acccess private
	* @return bool
	*/
	private function init(){
		if(isset($_COOKIE[$this->sessionname])){
			$this->session_id = $_COOKIE[$this->sessionname];
			$session = $this->get_session();
			if($session !== false){
				$last_activity = $session['last_activity'];
				if($last_activity !== false){
					$time = time();
					if($last_activity < $time-$this->session_length &&
							$this->session_length !== false){
						$this->destroy();
						return false;
					}
				}
			}
			return true;
		}
		$this->_create_session();
		return true;
	}

	/**
	* Retrieve key from session userdata
	*
	* @access public
	* @param string
	* @return mixed
	*/
	public function get($key){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session === false){
			return false;
		}

		if(isset($session['userdata'][$key])){
			return $session['userdata'][$key];
		}
		return false;
	}

	/**
	* Returns the session variable
	*
	* @access public
	* @return array
	*/
	public function dump_session(){
		if($this->session_id === false){
			return false;
		}

		return $this->get_session();
	}

	/**
	* Set userdata value
	*
	* @access public
	* @param string
	* @param mixed
	* @return bool
	*/
	public function set($key, $value){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session === false){
			return false;
		}

		$session['userdata'][$key] = $value;
		if($ret = $this->memcache->replace($this->session_id, $session) === false){
			return $this->memcache->set($this->session_id, $session);
		}
		return $ret;
	}

	/**
	* Delete key from userdata
	*
	* @access public
	* @param string
	* @return bool
	*/
	public function delete($key){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session === false){
			return false;
		}

		if(isset($session['userdata'][$key])){
			unset($session['userdata'][$key]);
			if($ret = $this->memcache->replace($this->session_id, $session) === false){
				return $this->memcache->set($this->session_id, $session);
			}
			return $ret;
		}
		else{
			return false;
		}
	}

	/**
	* Place temporary data in the session
	*
	* Places data in the session for default 1 request.  Cleaned out
	* automatically.  Can optionally specify the number of requests that
	* data should last for.
	*
	* @access public
	* @param string
	* @param mixed
	* @param int
	* @return bool
	*/
	public function set_flashdata($key, $value, $requests=1){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session == false){
			return false;
		}

		$data = array(
				'value' => $value,
				'length' => $requests
				);
		$session['flashdata'][$key] = $data;
		if($ret = $this->memcache->replace($this->session_id, $session) === false){
			return $this->memcache->set($this->session_id, $session);
		}
		return $ret;
	}

	/**
	* Retrieves a value from the flashdata
	*
	* @access public
	* @param string
	* @return mixed
	*/
	public function get_flashdata($key){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session === false){
			return false;
		}

		if(isset($session['flashdata'][$key])){
			return $session['flashdata'][$key]['value'];
		}
		return false;
	}

	/**
	* Extend the number of requests a single flashdata entry is kept for
	*
	* @access public
	* @param string
	* @param int
	* @return bool
	*/
	public function extend_flashdata($key, $extension=1){
		if($this->session_id === false){
			return false;
		}

		$session = $this->get_session();
		if($session === false){
			return false;
		}

		if(isset($session['flashdata'][$key])){
			$session['flashdata'][$key]['length'] += $extension;
			if($ret = $this->memcache->replace($this->session_id, $session) === false){
				return $this->memcache->set($this->session_id, $session);
			}
			return $ret;
		}
		return false;
	}

	/**
	* Returns the session from memcached
	*
	* @access private
	* @return mixed
	*/
	private function get_session(){
		return $this->memcache->get($this->session_id);
	}

	/**
	* Destroy current session
	*
	* @access public
	*/
	public function destroy(){
		if($this->session_id !== false){
			$this->memcache->delete($this->session_id);
			$this->session_id = false;
		}
		setcookie($this->sessionname, '', time()-3600);
	}

	/**
	* Connect to memcache servers
	*
	* @access private
	*/
	private function connect(){
		foreach($this->servers as $server){
			$this->memcache->addServer($server['host'], $server['port']);
		}
	}

	/**
	* Start new session
	*
	* @access private
	*/
	private function _create_session(){
		//$ua = $_SERVER['HTTP_USER_AGENT'];
		//$ip = $_SERVER['REMOTE_ADDR'];
		$time = time();

		//make session_id & ensure unique
		while(true){
			$sessid = hash(
				'sha512',
				uniqid(rand().$time,true)
				);
			if($this->memcache->get($sessid) === false){
				break;
			}
		}

		//cookie doesn't expire
		//setcookie($this->sessionname, $sessid, 0, '/');

		$this->session_id = $sessid;
		$data['last_activity'] = $time;
		$data['userdata'] = array();
		$data['flashdata'] = array();
		$this->memcache->set($this->session_id, $data);
	}

	/**
	* Deletes expired entries from flashdata
	*
	* @access private
	* @param array
	* @return array
	*/
	private function _clean_flashdata($flashdata){
		$flashdata_clean = array();
		foreach($flashdata as $key=>$value){
			$length = $value['length'];
			if($length <= 0){
				//wont append to clean array
				continue;
			}
			$length--;
			$data = array(
				'value' => $value['value'],
				'length' => $length
				);
			$flashdata_clean[$key] = $data;
			unset($flashdata[$key]);
		}
		return $flashdata_clean;
	}

	/**
	* Close connection to memcache servers
	*
	* @access private
	* @return bool
	*/
	private function close(){
		return $this->memcache->close();
	}

	/**
	* Class destructor
	*
	* Updates last activity value in memcache session
	*
	* @access public
	*/
	public function __destruct(){
		if($this->session_id !== false){
			$session = $this->get_session();
			$session['last_activity'] = time();
			if(isset($session['flashdata'])){
				$session['flashdata'] = $this->_clean_flashdata($session['flashdata']);
			}
			if($this->memcache->replace($this->session_id, $session) === false){
				$this->memcache->set($this->session_id, $session);
			}
		}
		$this->close();
	}
}

/* End of file Session.php */
