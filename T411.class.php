<?php
// v1 : rajouté à partir ligne 55 pour 'tronquer' le retour de request si on omet de préciser la requête pour search ...
class t411
{
	private $username = 'ArouG';
	private $password = 'my_password(à_changer_par_le_votre)';
	private $apiLink = 'https://api.t411.me';
	
	private $token;
	
	function __construct()
	{
		$this->connect();
	}
    	
	function connect()
	{
		$requete_post = array(
		'username' => $this->username,
		'password' => $this->password
		);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->apiLink.'/auth');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:x.x.x) Gecko/20041107 Firefox/x.x");
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $requete_post);
		
		$result = curl_exec($curl);
		curl_close($curl);
		
		$json = json_decode($result);
		$this->token = $json->token;
	}
	
	public function request($request, $post = null)
	{
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $this->apiLink.$request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:x.x.x) Gecko/20041107 Firefox/x.x");
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: '.$this->token));

		if ($post) 
		{
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}

		$res = curl_exec($curl);
		curl_close($curl);
    $findme='{"query":null';
    $p=strpos($res,$findme);   
    if ($p === false){
        return json_decode($res, true);
    } else {
        $r=substr($res,$p);
        return json_decode($r, true);
    }    
	}
}
?>
