<?php           
    set_time_limit(0);       
    require 't411.class.php';    
    $t411 = new t411();

    $db = new PDO('sqlite:t411stats.sqlite');
    
    // va chercher dernière ligne de la table résultats pour déterminer le nombre de tableaux de 1000 éléments parmi lesquels chercher
    // ainsi que la date du dernier scan
    $sql="SELECT DatMaint, nbpasses, NbLig FROM resultats ORDER BY DatMaint"; 
    $sth = $db->prepare($sql);
    $sth->execute();   
    $rec = $sth->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_LAST);     // le dernier est le dernier nombre de passes   
    $sth->closeCursor();                                           // sans doute non nécessaire mais c'est plus sûr !

    // on va tout stocker dans une table principale
    $A = array();
    $k=0;                                                          // pour noter l'indice
    $Aid=array(); $Aind=array();
    for ($p=0; $p<$rec['nbpasses']+3; $p++){                       // rajoutons 3 tableaux au cas où !!
        $off=1000*$p;
        $url="/torrents/search/a&offset=".$off."&limit=1000";  
        $list = $t411->request($url);
        for ($i=0; $i<count($list["torrents"]); $i++) {
            if ($list["torrents"][$i]["id"])
              {
                if ($list["torrents"][$i]["isVerified"] == 1)
                  {
                    $A[]=array(                                     // sans doute pas nécessaire mais ... sait-on jamais ?
                        "ind" => $k, 
                        "id" => $list["torrents"][$i]["id"], 
                        "seeders" => $list["torrents"][$i]["seeders"], 
                        "leechers" => $list["torrents"][$i]["leechers"], 
                        "times_completed" => $list["torrents"][$i]["times_completed"]
                        ); 
                    $Aid[]=$list["torrents"][$i]["id"];             // Aid = liste d'index des torrents
                    $Aind[$list["torrents"][$i]["id"]]=$k;          // Aind = fait correspondre un index de torrent à son indice dans le tableau $A
                    $k++;
                  }
              }
        }  
    }   

    // Précédent scan : on met les Ids torrents déjà scannés pour déterminer les nouveaux 'trous' découverts dans ce scan là
    $Ind=array();
    $sql="SELECT Tid from scans WHERE Date_Scan=".$rec['DatMaint'];
    $sth = $db->prepare($sql);
    $sth->execute();
    $Indd = $sth->fetchAll(PDO::FETCH_ASSOC);                        
    for ($k=0; $k<count($Indd); $k++) $Ind[]=$Indd[$k]['Tid'];        // c'est la table $Ind qui contiendra les Ids du scan précédent ... array_values aurait été plus clair !
    //$Ind=array_values($Indd);
    unset($Indd);                                                      // fait de la place pour la mémoire.
    
    $now=time();                                                      // DateHeure de maintenant      
    $tmp=array_values(array_intersect($Ind,$Aid));                    // tmp ne retient que les Ids du nouveau scan correspondants au précédent
    $Ress=array_diff($Ind,$tmp);                                      // et Ress fait la différence entre les 2, ç-à-d ; les trous !  
    $Res=array();                                   
    $Res=array_values($Ress);
    unset($Ress);                                                     // fait de la place pour la mémoire.

    $imax=0;                                                          // initialise l'indice maximal correspondant au dernier torrent dans $A
    for ($i=0; $i<count($tmp); $i++)
    {                                                                 // mise à jour de la table scans
        if ($tmp[$i]){                                                
            $J=$Aind[$tmp[$i]];                                           // indice dans la table A
            $A_Tid=$A[$J]['id']; $A_NbS=$A[$J]['seeders']; $A_NbL=$A[$J]['leechers']; $A_NbC=$A[$J]['times_completed'];           
            $sql="INSERT into scans (Tid, Date_Scan, NbS, NbL, NbC) values (".$A_Tid.",".$now.",".$A_NbS.",".$A_NbL.",".$A_NbC.")";
            $nimp=$db->exec($sql);
      
            if ($J > $imax) $imax=$J;                                     // ne retient que le plus grand
        }    
    }       
    
    $vide=array("error" => "Torrent not found", "code" => 301 );      // vide = retour de la requête detail si disparu (RIP)
    $v=0; $pb=array();                                                // pb regroupera ceux 'non morts'
    if (count($Res) > 0){     //torrent non trouvés
        for ($p=0; $p<count($Res); $p++){
            $det=$t411->request("/torrents/details/".$Res[$p]);
            if ($vide==$det)                                          // bien mort !
            {                                                         // enregistre parmi les morts
                $sql="INSERT INTO morts (DatMaint, id) VALUES (".$now.",".$Res[$p].")";  
                $sth = $db->prepare($sql);
                $sth->execute();
            } 
              else 
            {                                                         // là, c'est un revenant : trou toujours présent    
                $sql="INSERT INTO mpb (DatMaint, id) VALUES (".$now.",".$Res[$p].")";                                      
                echo $sql."\n<br>";
                $sth = $db->prepare($sql);
                $sth->execute();
            }   
        }
    }            
     
    $nbpages= (int)($imax / 1000) +1;
    $sql="INSERT into resultats (DatMaint, NbLig, nbpasses) values (".$now.",".count($tmp).",".$nbpages.")"; 
    $sth = $db->prepare($sql);
    $sth->execute();

    // nettoyage final et cloture de la baase !
    $sql="VACUUM";
    $nimp=$db->exec($sql);
    $db=Null;
?>
