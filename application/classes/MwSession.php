<?php



class MwSession {


    public $savePath;

    function MwSession(){
		
	$sessionpath = session_save_path();
	 
	if (strpos ($sessionpath, ";") !== FALSE){
  		$sessionpath = substr ($sessionpath, strpos ($sessionpath, ";")+1);
	}
	
	if($sessionpath == ''){
	$sessionpath =  DBPATH_FULL.'session'.DS;	
	}
	
	 $this->savePath = $sessionpath;
	 
	}

    function open($savePath, $sessionName)
    {


 
		
		 if($savePath != false and trim($savePath) != ''){
			 
				$this->savePath = $savePath;
				
 
		 }
     
        if (!is_dir($this->savePath)) {
            mkdir_recursive($this->savePath);
        }

        return true;
    }

    function close()
    {
        return true;
    }

    function read($id)
    {
        return (string)@file_get_contents($this->savePath.DS."sess_$id");
    }

    function write($id, $data)
    {

        if(!is_dir($this->savePath)){
            mkdir_recursive($this->savePath);

        }
         if(!is_file($this->savePath.DS.'index.php')){
            @touch($this->savePath.DS.'index.php');
         }
 
 
 
 
		$sess_file = $this->savePath.DS."sess_$id";
		 
		
		  if ($fp = @fopen($sess_file, "w")) {
		   flock($fp,LOCK_EX);
		   $results=fwrite($fp, $data);
		   flock($fp,LOCK_UN);
		   return($results);
		  } else {
		   return(false);
		  }
		 
 



        return file_put_contents($this->savePath.DS."sess_$id", $data) === false ? false : true;
    }

    function destroy($id)
    {
        $file = $this->savePath.DS."sess_$id";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    function gc($maxlifetime)
    {
        foreach (glob($this->savePath.DS."sess_*") as $file) {
            if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }
}
