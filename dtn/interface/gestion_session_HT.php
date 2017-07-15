<?php
// Variable de connexion � HT
define("CONSUMERKEY",'GVTI3L4KQDr97DCibIHwWq');
define("CONSUMERSECRET",'FImiceJW7lscXkHtGp85XfYCiS9eHBcXoKBZPh14miW');
require_once($_SERVER['DOCUMENT_ROOT']."/dtn/interface/includes/serviceEquipes.php");



/******************************************************************************/
/******************************************************************************/
/*      TEST CONNECTIVITE HT                                                  */
/******************************************************************************/
/******************************************************************************/

if (isset($_SESSION['HTCP']) && $_SESSION['HTCP']->pingChppServer(5000)==false) { // Si le serveur CHPP est inaccessible durant 10 secondes
   $_SESSION['CHPP_KO'] = true;
   unset($_SESSION['HTCP']);
} else {
   $_SESSION['CHPP_KO'] = false;
}



/******************************************************************************/
/******************************************************************************/
/*      GESTION DONNEES FORMULAIRE                                            */
/******************************************************************************/
/******************************************************************************/

if (!isset($_SESSION['horsConnexion'])) {

  // Si l'utilisateur a cliqu� sur ouvrir une session HT
  if (isset($_REQUEST['mode'])) {
  
    if ($_REQUEST['mode']=='redirectionHT') {
  
      if (isset($_REQUEST['connexion_permanente']) && $_REQUEST['connexion_permanente']==1) {
        $callbackUrl .= "&connexion_permanente=1";
      } else {
        $callbackUrl .= "&connexion_permanente=0";
      }
    
      /*
      You must supply your chpp crendentials and a callback url.
      User will be redirected to this url after login
      You can add your own parameters to this url if you need,
      they will be kept on user redirection
      */
      try
      {
        $HT = new CHPPConnection(CONSUMERKEY,CONSUMERSECRET,$callbackUrl);
        $url = $HT->getAuthorizeUrl();
      }
      catch(HTError $e)
      {
        echo $e->getMessage();
      }
      /*
      Be sure to store $HT in session before redirect user
      to Hattrick chpp login page
      */
      $_SESSION['HTCP'] = $HT;
      /*
      Redirect user to Hattrick for login
      or put a link with this url on your site
      */
      header('Location: '.$url); 
			exit();
    }
    
    
    /******************************************************************************/
    /******************************************************************************/
    /*      GESTION RETOUR APRES CONNEXION HT                                     */
    /******************************************************************************/
    /******************************************************************************/
    
    if ($_REQUEST['mode']=='retour' && isset($_SESSION['HTCP'])) {
  
      // On r�cup�re les donn�es d'authentification pass� dans l'url
      try
      {
        $_SESSION['HTCP']->retrieveAccessToken($_REQUEST['oauth_token'], $_REQUEST['oauth_verifier']);
        /*
        Now access is granted for your application
        You can save user token and token secret and/or request xml files
        */
		$userId = $_SESSION['HTCP']->getClub()->getUserId();
		$teamNb = $_SESSION['HTCP']->getNumberOfTeams($userId);
		for ($tsidx=$teamNb-1; $tsidx >= 0; $tsidx--) {
			// On commence par le 2e club s'il y a pour faire la maj
			$team2 = $_SESSION['HTCP']->getSecondaryTeam($userId, $tsidx);
			if ($team2 != null)
			{
				$clubHT = getDataClubFromHT_usingPHT($team2->getTeamId());
				$clubHT['userToken'] = $_SESSION['HTCP']->getOauthToken();
				$clubHT['userTokenSecret'] = $_SESSION['HTCP']->getOauthTokenSecret();

				$majClub=insertionClub($clubHT); // Insertion ou Maj des tokens dans la bdd DTN
			}        
        }
        $clubHT = getDataClubFromHT_usingPHT($_SESSION['HTCP']->getTeam()->getTeamId()); // On r�cup�re sur HT les informations sur le club connect�
        $clubHT['userToken'] = $_SESSION['HTCP']->getOauthToken();
        $clubHT['userTokenSecret'] = $_SESSION['HTCP']->getOauthTokenSecret();
  
        $majClub=insertionClub($clubHT); // Insertion ou Maj des tokens dans la bdd DTN
        
        $_SESSION['nomUser']=$clubHT['nomUser'];
        $_SESSION['idUserHT']=$clubHT['idUserHT'];
        $_SESSION['newVisit']=1;
        
        if (isset($_REQUEST['connexion_permanente']) && $_REQUEST['connexion_permanente']==1) {
          // Si la case "garder ma session active" est coch�e alors on ajoute un cookie valable 5 ans
          setcookie('idClubHT',$clubHT['idClubHT'], time() + 5*365*24*3600, null, null, false, true);        
        } else if (isset($_REQUEST['connexion_permanente']) && $_REQUEST['connexion_permanente']==0) {
          // Si la case "garder ma session active" est d�coch�e alors on supprime les cookies
          setcookie('idClubHT');
        }
      }
      catch(HTError $e)
      {
        echo $e->getMessage();
      }
      
    }
    
    if ($_REQUEST['mode']=='logout') {
      setcookie('idClubHT');
      unset($_SESSION['HTCP']);
      unset($_SESSION['nomUser']);
      unset($_SESSION['idUserHT']);
      unset($_SESSION['newVisit']);
    }
     
  } else { // $_REQUEST['mode'] n'est pas d�fini
  
    if (!isset($_SESSION['HTCP']) || empty($_SESSION['HTCP']) ) {
  
      /******************************************************************************/
      /******************************************************************************/
      /*      RECUPERATION SESSION DES COOKIES - UTILISE SUR PORTAIL                */
      /******************************************************************************/
      /******************************************************************************/
      if (isset($_COOKIE['idClubHT']) && !empty($_COOKIE['idClubHT']) && $_SESSION['acces']=='PORTAIL') {
  
        //echo("<br />===PORTAIL===<br />");
        $HT = new CHPPConnection(CONSUMERKEY,CONSUMERSECRET);
        $clubDTN = getClubID($_COOKIE['idClubHT']);
  
        $HT->setOauthToken($clubDTN['userToken']);
        $HT->setOauthTokenSecret($clubDTN['userTokenSecret']);
      
        $_SESSION['HTCP']=$HT;
        unset($HT);
        $_SESSION['nomUser']=$clubDTN['nomUser'];
        $_SESSION['idUserHT']=$clubDTN['idUserHT'];
      }
      
      /******************************************************************************/
      /******************************************************************************/
      /*      RECUPERATION SESSION DANS BDD - UTILISE SUR INTERFACE                 */
      /******************************************************************************/
      /******************************************************************************/
      if ($_SESSION['acces']=='INTERFACE') {
  
        $_SESSION['HTCP']=existAutorisationClub(null,$_SESSION['sesUser']['idAdminHT']);
  
        if (!$_SESSION['HTCP']) {
          unset($_SESSION['HTCP']);
        } else {
          $clubDTN = getClubID(null,$_SESSION['sesUser']['idAdminHT']); // Extraction du club dans la bdd DTN
          $_SESSION['nomUser']=$clubDTN['nomUser'];
          $_SESSION['idUserHT']=$clubDTN['idUserHT'];
        }
        /*echo("<br />===INTERFACE===<br />");
        echo("idAdminHT=");var_dump($_SESSION['sesUser']['idAdminHT']);
        echo("<br />session=");var_dump($_SESSION['HTCP']);*/
        
      }
  
    }
    
    if (isset($_SESSION['HTCP'])) {
      /******************************************************************************/
      /******************************************************************************/
      /*      VERIFICATION VALIDITE SESSION                                         */
      /******************************************************************************/
      /******************************************************************************/
  
      // V�rifier que la session est valide
      $check = $_SESSION['HTCP']->checkToken();
      //var_dump($check);echo("<br><br>".$check->isValid());exit;
  
      if ($check->isValid()===false) {
        // Si session non Valide alors d�truire la session
        unset($_SESSION['HTCP']);
        unset($_SESSION['nomUser']);
        unset($_SESSION['idUserHT']);
        unset($_SESSION['newVisit']);
        setcookie('idClubHT');
      }
    }
  
  }
  
  // Initialistion de la variable de session newVisit afin d'emp�cher les proprios de faire une maj en base � chaque clique sur proposer.
  // Une seule maj par session 
  if (!isset($_SESSION['newVisit']) && $_SESSION['acces']=='PORTAIL') {$_SESSION['newVisit']=1;}
  
  //print_r($_SESSION['HTCP']);
}


if (isset($_SESSION['HTCP']) && $_SESSION['HTCP']->pingChppServer(5000)==false) { // Si le serveur CHPP est inaccessible durant 10 secondes
   $_SESSION['CHPP_KO'] = true;
   unset($_SESSION['HTCP']);
} else {
   $_SESSION['CHPP_KO'] = false;
}


?>
