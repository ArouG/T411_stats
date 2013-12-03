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

    // sélectionne les catégories par type d'age des torrents
    $sqn="SELECT B.Uic, A.NbS, A.NbL, A.nbC  FROM scans AS A, base AS B, normals AS C WHERE ((B.Tid=C.Tid) and (C.Tid=A.Tid)) ORDER by B.Uic"; 
    $sqr="SELECT B.Uic, A.NbS, A.NbL, A.nbC  FROM scans AS A, base AS B, recents AS C WHERE ((B.Tid=C.Tid) and (C.Tid=A.Tid)) ORDER by B.Uic;";   
    $sqo="SELECT B.Uic, A.NbS, A.NbL, A.nbC  FROM scans AS A, base AS B, oldests AS C WHERE ((B.Tid=C.Tid) and (C.Tid=A.Tid)) ORDER by B.Uic;"; 
       
    $sqle="DELETE from synth";    // nettoit tout
    $sth = $db->prepare($sqle);
    $sth->execute(); 
    $sth->CloseCursor();    
                                                  
    $sth= $db->prepare($sqn);      // récupère 3 tables en fonctions des 3 populations de torrents
    $sth->execute();  
    $Indn = $sth->fetchAll(PDO::FETCH_ASSOC);   
    $sth= $db->prepare($sqr);
    $sth->execute();              
    $Indr = $sth->fetchAll(PDO::FETCH_ASSOC);    
    $sth= $db->prepare($sqo);
    $sth->execute(); 
    $Indo = $sth->fetchAll(PDO::FETCH_ASSOC);     
    
    $A=array();   $I=array();    
    //               Examen première table pour sauvegarder dans synth ce qui se rapporte à la table normals
    $Sm=0; $SM=0; $Lm=0; $LM=0; $Cm=0; $CM=0; $OldC=0;
    for ($p=0; $p<count($Indn); $p++){    
        if ($Indn[$p]['Uic'] != $OldC){          // si on change de catégories
           if ($p>0){                            // on commence par sauvegarder les valeurs précédentes
                $sqlp="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'N',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";
                $sth= $db->prepare($sqlp);
                $sth->execute();  
                $A[$OldC]=array();
                $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM);  
                $I[]=$OldC; 
           }                     
           $OldC=$Indn[$p]['Uic']; $Sm=$Indn[$p]['NbS']; $SM=$Indn[$p]['NbS']; $Lm=$Indn[$p]['NbL']; $LM=$Indn[$p]['NbL']; $Cm=$Indn[$p]['NbC']; $CM=$Indn[$p]['NbC'];
        } else {                                 // dans la même catégorie, on filtre pour conserver les min et max
            if ($Indn[$p]['NbS'] < $Sm) $Sm=$Indn[$p]['NbS'];  
            if ($Indn[$p]['NbS'] > $SM) $SM=$Indn[$p]['NbS'];  
            if ($Indn[$p]['NbL'] < $Lm) $Lm=$Indn[$p]['NbL'];  
            if ($Indn[$p]['NbL'] > $LM) $LM=$Indn[$p]['NbL'];  
            if ($Indn[$p]['NbC'] < $Cm) $Cm=$Indn[$p]['NbC'];  
            if ($Indn[$p]['NbC'] > $CM) $CM=$Indn[$p]['NbC'];    
        }
    }  
    // sans oublier de sauvegarder la dernière ligne !  
    $sqlp="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'N',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")"; 
    $sth= $db->prepare($sqlp);
    $sth->execute(); 
    $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM); 
    $I[]=$OldC;           
    
    //               Passons à la seconde table : oldests
    $Sm=0; $SM=0; $Lm=0; $LM=0; $Cm=0; $CM=0; $OldC=0;
    for ($p=0; $p<count($Indo); $p++){
        if ($Indo[$p]['Uic'] != $OldC){
           if ($p>0){
                $sqlq="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'O',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";  
                $sth= $db->prepare($sqlq);
                $sth->execute();     
                if (!in_array($OldC, $I)) $I[]=$OldC;             // $I va lister et indexer toutes les catégories. Ne créer un nouvel enregistrement que si pas encore disponible
                $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM); 
           }                     
           $OldC=$Indo[$p]['Uic']; $Sm=$Indo[$p]['NbS']; $SM=$Indo[$p]['NbS']; $Lm=$Indo[$p]['NbL']; $LM=$Indo[$p]['NbL']; $Cm=$Indo[$p]['NbC']; $CM=$Indo[$p]['NbC'];
        } else {
            if ($Indo[$p]['NbS'] < $Sm) $Sm=$Indo[$p]['NbS'];  
            if ($Indo[$p]['NbS'] > $SM) $SM=$Indo[$p]['NbS'];  
            if ($Indo[$p]['NbL'] < $Lm) $Lm=$Indo[$p]['NbL'];  
            if ($Indo[$p]['NbL'] > $LM) $LM=$Indo[$p]['NbL'];  
            if ($Indo[$p]['NbC'] < $Cm) $Cm=$Indo[$p]['NbC'];  
            if ($Indo[$p]['NbC'] > $CM) $CM=$Indo[$p]['NbC'];    
        }
    }    
    $sqlq="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'O',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";  
    $sth= $db->prepare($sqlq);    
    $sth->execute();    
    if (!in_array($OldC, $I)) $I[]=$OldC;            
    $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM); 
    
    //               et enfin la dernière : oldests
    $Sm=0; $SM=0; $Lm=0; $LM=0; $Cm=0; $CM=0; $OldC=0;
    for ($p=0; $p<count($Indr); $p++){
        if ($Indr[$p]['Uic'] != $OldC){
           if ($p>0){
                $sqlp="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'R',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";  
                $sth= $db->prepare($sqlp);
                $sth->execute();    
                if (!in_array($OldC, $I)) $I[]=$OldC;   
                $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM); 
           }                     
           $OldC=$Indr[$p]['Uic']; $Sm=$Indr[$p]['NbS']; $SM=$Indr[$p]['NbS']; $Lm=$Indr[$p]['NbL']; $LM=$Indr[$p]['NbL']; $Cm=$Indr[$p]['NbC']; $CM=$Indr[$p]['NbC'];
        } else {
            if ($Indr[$p]['NbS'] < $Sm) $Sm=$Indr[$p]['NbS'];  
            if ($Indr[$p]['NbS'] > $SM) $SM=$Indr[$p]['NbS'];  
            if ($Indr[$p]['NbL'] < $Lm) $Lm=$Indr[$p]['NbL'];  
            if ($Indr[$p]['NbL'] > $LM) $LM=$Indr[$p]['NbL'];  
            if ($Indr[$p]['NbC'] < $Cm) $Cm=$Indr[$p]['NbC'];  
            if ($Indr[$p]['NbC'] > $CM) $CM=$Indr[$p]['NbC'];    
        }
    }         
    $sqlp="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$OldC.", 'R',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";  
    $sth= $db->prepare($sqlp);      
    $sth->execute();    
    if (!in_array($OldC, $I)) $I[]=$OldC;  
    $A[$OldC][]=array($Sm,$SM,$Lm,$LM,$Cm,$CM); 
        
    // Dernière boucle pour disposer des mêmes informations mais pour l'ensemble des statistiques : on englobe les trois tables en quelque sorte        
    for ($p=0; $p<count($I); $p++){            // balayage de chaque catégorie
              $Sm=$A[$I[$p]][0][0]; $SM=$A[$I[$p]][0][1]; $Lm=$A[$I[$p]][0][2]; $LM=$A[$I[$p]][0][3]; $Cm=$A[$I[$p]][0][4]; $CM=$A[$I[$p]][0][5]; 
              for ($j=1; $j<count($A[$I[$p]]); $j++){                    // pour chacune on balaye sur l'âge des torrents
                  if ($A[$I[$p]][$j][0] < $Sm) $Sm=$A[$I[$p]][$j][0]; 
                  if ($A[$I[$p]][$j][1] > $SM) $SM=$A[$I[$p]][$j][1];  
                  if ($A[$I[$p]][$j][2] < $Lm) $Lm=$A[$I[$p]][$j][2]; 
                  if ($A[$I[$p]][$j][3] > $LM) $LM=$A[$I[$p]][$j][3];
                  if ($A[$I[$p]][$j][4] < $Cm) $Cm=$A[$I[$p]][$j][4]; 
                  if ($A[$I[$p]][$j][5] > $CM) $CM=$A[$I[$p]][$j][5];
              }  
              // et on sauvegarde avec un "tableau" A comme tous (All)
              $sqlp="INSERT INTO synth (Uic, tableau, Smin, Smax, Lmin, Lmax, Cmin, Cmax) VALUES (".$I[$p].", 'A',".$Sm.", ".$SM.", ".$Lm.", ".$LM.", ".$Cm.", ".$CM.")";  
              $sth= $db->prepare($sqlp);
              $sth->execute();               
    }       


    // nettoyage final et cloture de la baase !
    $sql="VACUUM";
    $nimp=$db->exec($sql);
    $db=Null;
?>
