<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2011                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/layer');
include_spip('inc/texte'); // inclut inc_filtre
include_spip('inc/quete_calendrier');

//  Typographie generale des calendriers de 3 type: jour/semaine/mois(ou plus)

// Notes: pour toutes les fonctions ayant parmi leurs parametres
// annee, mois, jour, echelle, partie_cal, script, ancre
// ceux-ci apparaissent TOUJOURS dans cet ordre 

define('DEFAUT_D_ECHELLE',120); # 1 pixel = 2 minutes

define('DEFAUT_PARTIE_M', "matin");
define('DEFAUT_PARTIE_S', "soir");
define('DEFAUT_PARTIE_T', "tout");
define('DEFAUT_PARTIE_R', "sansheure");
define('DEFAUT_PARTIE', DEFAUT_PARTIE_R);

// 
// Utilitaires sans html ni sql
//


// tous les liens de navigations sont issus de cette fonction
// on peut definir generer_url_date et un htacces pour simplifier les URL

// http://doc.spip.org/@calendrier_args_date
function calendrier_args_date($script, $annee, $mois, $jour, $type, $finurl) {
	if (function_exists('generer_url_date'))
		return generer_url_date($script, $annee, $mois, $jour, $type, $finurl);

	$script = parametre_url($script, 'annee', sprintf("%04d", $annee));
	$script = parametre_url($script, 'mois',  sprintf("%02d", $mois));
	$script = parametre_url($script, 'jour',  sprintf("%02d", $jour));
	$script = parametre_url($script, 'type',  $type);
	return $script . $finurl;
}

// http://doc.spip.org/@calendrier_href
function calendrier_href($script, $annee, $mois, $jour, $type, $fin, $ancre, $img, $titre, $class='', $alt='', $clic='', $style='', $evt='')
{
	$h = calendrier_args_date($script, $annee, $mois, $jour, $type, $fin);

	if ($img) $clic =  http_img_pack($img, ($alt ? $alt : $titre), $class ? " class=\"$class\"" : '');

	// pas d'Ajax pour l'espace public pour le moment ou si indispo
	$moi = preg_match("/exec=" . _request('exec') .'$/', $script);
	if (test_espace_prive() && $moi && (_SPIP_AJAX === 1 ))
		$evt .= "\nonclick=" . ajax_action_declencheur($h,$ancre);

	$h .= ($ancre ? "#$ancre" : '');
	return http_href("$h", $clic, $titre, $style, $class, $evt);
}

// Fabrique une balise A, avec tous les attributs possibles
// attention au cas ou la href est du Javascript avec des "'"
// pour un href conforme au validateur W3C, faire & --> &amp; avant

// http://doc.spip.org/@http_href
function http_href($href, $clic, $title='', $style='', $class='', $evt='') {
	if ($style) $evt .= " style='$style'";
	return lien_ou_expose($href, $clic, false, $class, $title, 'nofollow', $evt);
}

# prend une heure de debut et de fin, ainsi qu'une echelle (seconde/pixel)
# et retourne un tableau compose
# - taille d'une heure
# - taille d'une journee
# - taille de la fonte
# - taille de la marge

// http://doc.spip.org/@calendrier_echelle
function calendrier_echelle($debut, $fin, $echelle)
{
  if ($echelle==0) $echelle = DEFAUT_D_ECHELLE;
  if ($fin <= $debut) $fin = $debut +1;

  $duree = $fin - $debut;
  $dimheure = floor((3600 / $echelle));
  return array($dimheure,
	       (($duree+2) * $dimheure),
	       floor (14 / (1+($echelle/240))),
	       floor(240 / $echelle));
}

# Calcule le "top" d'une heure

// http://doc.spip.org/@calendrier_top
function calendrier_top ($heure, $debut, $fin, $dimheure, $dimjour, $fontsize) {
	
	$h_heure = substr($heure, 0, strpos($heure, ":"));
	$m_heure = substr($heure, strpos($heure,":") + 1, strlen($heure));
	$heure100 = $h_heure + ($m_heure/60);

	if ($heure100 < $debut) $heure100 = ($heure100 / $debut) + $debut - 1;
	if ($heure100 > $fin) $heure100 = (($heure100-$fin) / (24 - $fin)) + $fin;

	$top = floor(($heure100 - $debut + 1) * $dimheure);

	return $top;	
}

# Calcule la hauteur entre deux heures
// http://doc.spip.org/@calendrier_height
function calendrier_height ($heure, $heurefin, $debut, $fin, $dimheure, $dimjour, $fontsize) {

	$height = calendrier_top ($heurefin, $debut, $fin, $dimheure, $dimjour, $fontsize) 
				- calendrier_top ($heure, $debut, $fin, $dimheure, $dimjour, $fontsize);

	$padding = floor(($dimheure / 3600) * 240);
	$height = $height - (2* $padding + 2); // pour padding interieur
	
	if ($height < ($dimheure/4)) $height = floor($dimheure/4); // eviter paves totalement ecrases
	
	return $height;	
}

//
// init: calcul generique des evenements a partir des tables SQL
//


# dispose les evenements d'une semaine

// Conversion d'un tableau de champ ics en des balises div positionnees    
// Le champ categories indique la Classe de CSS a prendre
// $echelle est le nombre de secondes representees par 1 pixel

// http://doc.spip.org/@http_calendrier_ics
function http_calendrier_ics($annee, $mois, $jour,$echelle, $partie_cal,  $largeur, $evt, $style='', $class='') {
	global $spip_lang_left;

	// tableau
	if ($partie_cal == DEFAUT_PARTIE_S) {
		$debut = 12;
		$fin = 23;
	} else if ($partie_cal == DEFAUT_PARTIE_M) {
		$debut = 4;
		$fin = 15;
	} else {
		$debut = 7;
		$fin =21;
	}
	
	if ($echelle==0) $echelle = DEFAUT_D_ECHELLE;

	list($dimheure, $dimjour, $fontsize, $padding) =
	  calendrier_echelle($debut, $fin, $echelle);
	$modif_decalage = round($largeur/8);

	$date = date("Ymd", mktime(0,0,0,$mois, $jour, $annee));
	list($sansheure, $avecheure) = $evt;
	$avecheure = isset($avecheure[$date]) ? $avecheure[$date] : array();
	$sansheure = isset($sansheure[$date]) ? $sansheure[$date] : array();

	$total = '';

	if ($avecheure)
    {
		$tous = 1 + count($avecheure);
		$i = $bas_prec = 0;
		foreach($avecheure as $evenement){

			$d = $evenement['DTSTART'];
			$e = $evenement['DTEND'];
			$d_jour = substr($d,0,8);
			$e_jour = $e ? substr($e,0,8) : $d_jour;
			$debut_avant = false;
			$fin_apres = false;
			
			/* disparues sauf erreur
			 $radius_top = " radius-top";
			$radius_bottom = " radius-bottom";
			*/
			if ($d_jour <= $date AND $e_jour >= $date)
			{

			$i++;

			// Verifier si debut est jour precedent
			if (substr($d,0,8) < $date)
			{
				$heure_debut = 0; $minutes_debut = 0;
				$debut_avant = true;
				$radius_top = "";
			}
			else
			{
				$heure_debut = substr($d,-6,2);
				$minutes_debut = substr($d,-4,2);
			}

			if (!$e)
			{ 
				$heure_fin = $heure_debut ;
				$minutes_fin = $minutes_debut ;
				$bordure = "border-bottom: dashed 2px";
			}
			else
			{
				$bordure = "";
				if (substr($e,0,8) > $date) 
				{
					$heure_fin = 23; $minutes_fin = 59;
					$fin_apres = true;
					$radius_bottom = "";
				}
				else
				{
					$heure_fin = substr($e,-6,2);
					$minutes_fin = substr($e,-4,2);
				}
			}
			
			if ($debut_avant && $fin_apres)  $opacity = "-moz-opacity: 0.6; filter: alpha(opacity=60);";
			else $opacity = "";
						
			$haut = calendrier_top ("$heure_debut:$minutes_debut", $debut, $fin, $dimheure, $dimjour, $fontsize);
			$bas =  !$e ? $haut :calendrier_top ("$heure_fin:$minutes_fin", $debut, $fin, $dimheure, $dimjour, $fontsize);
			$hauteur = calendrier_height ("$heure_debut:$minutes_debut", "$heure_fin:$minutes_fin", $debut, $fin, $dimheure, $dimjour, $fontsize);

			if ($bas_prec >= $haut) $decale += $modif_decalage;
			else $decale = (4 * $fontsize);
			if ($bas > $bas_prec) $bas_prec = $bas;
			
			$url = isset($evenement['URL'])
			  ? $evenement['URL'] : ''; 
			$desc = propre($evenement['DESCRIPTION']);
			$perso = substr($evenement['ATTENDEE'], 0,strpos($evenement['ATTENDEE'],'@'));
			$lieu = isset($evenement['LOCATION']) ?
				$evenement['LOCATION'] : '';
			$u = $GLOBALS['meta']['pcre_u'];
			$sum = typo($evenement['SUMMARY']);
			if (!$sum) { $sum = $desc; $desc = '';}
			if (!$sum) { $sum = $lieu; $lieu = '';}
			if (!$sum) { $sum = $perso; $perso = '';}
			if ($sum)
			  $sum = "<span class='calendrier-verdana10'><span  style='font-weight: bold;'>$sum</span>$lieu $perso</span>";
			if (($largeur > 90) && $desc)
			  $sum .=  "\n<br /><span class='calendrier-noir'>$desc</span>";
			$colors = $evenement['CATEGORIES'];
		  $sum = ((!$url) ? $sum : http_href(quote_amp($url), $sum, $desc,"border: 0px",$colors));
			$sum = pipeline('agenda_rendu_evenement',array('args'=>array('evenement'=>$evenement,'type'=>'ics'),'data'=>$sum));

			$total .= "\n<div class='calendrier-arial10 $colors' 
	style='cursor: auto; position: absolute; overflow: hidden;$opacity z-index: " .
				$i .
				"; $spip_lang_left: " .
				$decale .
				"px; top: " .
				$haut .
				"px; height: " .
				$hauteur .
				"px; width: ".
				($largeur - 2 * ($padding+1)) .
				"px; font-size: ".
				floor($fontsize * 1.3) .
				"px; padding: " .
				$padding . 
				"px; $bordure'
	onmouseover=\"this.style.zIndex=" . $tous . "\"
	onmouseout=\"this.style.zIndex=" . $i . "\">" .
			  $sum . 
				"</div>";
			}
		}
    }
	return
	   "\n<div class='calendrier-jour$class' style='height: ${dimjour}px; font-size: ${fontsize}px;$style'>\n" .
	  http_calendrier_ics_grille($debut, $fin, $dimheure, $dimjour, $fontsize) .
	  $total .
	  "\n</div>" .
	  (!$sansheure ? "" :
	   http_calendrier_ics_trois($sansheure, $largeur, $dimjour, $fontsize, '')) ;

}

# Affiche une grille horaire 
# Selon l'echelle demandee, on affiche heure, 1/2 heure 1/4 heure, 5minutes.

// http://doc.spip.org/@http_calendrier_ics_grille
function http_calendrier_ics_grille($debut, $fin, $dimheure, $dimjour, $fontsize)
{
	global $spip_lang_left, $spip_lang_right;
	$slice = floor($dimheure/(2*$fontsize));
	if ($slice%2) $slice --;
	if (!$slice) $slice = 1;

	$total = '';
	for ($i = $debut; $i < $fin; $i++) {
		$c = " class='calendrier-heurepile'";
		for ($j=0; $j < $slice; $j++) 
		{
			$n = calendrier_top ("$i:".sprintf("%02d",floor(($j*60)/$slice)), $debut, $fin, $dimheure, $dimjour, $fontsize);
			$total .= "\n<div$c style='$spip_lang_left: 0px; top: $n" ."px;'>$i:" .
				sprintf("%02d",floor(($j*60)/$slice)) . 
				"</div>";
			$c = " class='calendrier-heureface'";
		}
	}

	$n = calendrier_top ("$fin:00", $debut, $fin, $dimheure, $dimjour, $fontsize);

	$c = ($dimjour - $fontsize - 2);

	return "\n<div class='calendrier-heurepile' style='border: 0px; $spip_lang_left: 0px; top: 2px;'>0:00</div>" .
		$total .
		"\n<div class='calendrier-heurepile' style='$spip_lang_left: 0px; top: $n"."px;'>$fin:00</div>" .
		"\n<div class='calendrier-heurepile' style='border: 0px; $spip_lang_left: 0px; top: $c"."px;'>23:59</div>";
}

# si la largeur le permet, les evenements sans duree, 
# se placent a cote des autres, sinon en dessous

// http://doc.spip.org/@http_calendrier_ics_trois
function http_calendrier_ics_trois($evt, $largeur, $dimjour, $fontsize, $border)
{
	global $spip_lang_left; 

	$types = array();
	foreach($evt as $v)
	  $types[isset($v['DESCRIPTION']) ? 'info_articles' :
		 (isset($v['DTSTART']) ? 'info_liens_syndiques_3' :
		  'info_breves_02')][] = $v;
	$res = '';
	foreach ($types as $k => $v) {
	  $res2 = '';
	  foreach ($v as $evenement) {
	    $res2 .= http_calendrier_sans_heure($evenement);
	  }
	  $res .= "\n<div class='calendrier-verdana10 calendrier-titre'>".
	    _T($k) .
	    "</div>" .
	    $res2;
	}
		
	if ($largeur > 90) {
		$largeur += (5*$fontsize);
		$pos = "-$dimjour";
	} else { $largeur = (3*$fontsize); $pos= 0; }
	  
	return "\n<div style='position: relative; z-index: 2; top: ${pos}px; margin-$spip_lang_left: " . $largeur . "px'>$res</div>";
}

// http://doc.spip.org/@http_calendrier_ics_titre
function http_calendrier_ics_titre($annee, $mois, $jour,$script, $finurl='', $ancre='')
{
	$date = mktime(0,0,0,$mois, $jour, $annee);
	$jour = date("d",$date);
	$mois = date("m",$date);
	$annee = date("Y",$date);
	$nom = affdate_jourcourt("$annee-$mois-$jour");
	return "<div class='calendrier-arial10 calendrier-titre'>" .
	  calendrier_href($script, $annee, $mois, $jour, 'jour', $finurl, $ancre, '', $nom, 'calendrier-noir','',$nom) .
	  "</div>";
}


// http://doc.spip.org/@http_calendrier_sans_heure
function http_calendrier_sans_heure($ev)
{
	$desc = propre($ev['DESCRIPTION']);
	$sum = typo($ev['SUMMARY']);
	if (!$sum) $sum = $desc;
	$i = isset($ev['DESCRIPTION']) ? 11 : 9; // 11: article; 9:autre
	if ($ev['URL'])
	  $sum = http_href(quote_amp($ev['URL']), $sum, $desc);
	$sum = pipeline('agenda_rendu_evenement',array('args'=>array('evenement'=>$ev,'type'=>'sans_heure'),'data'=>$sum));
	return "\n<div class='calendrier-arial$i calendrier-evenement'>" .
	  "<span class='" . $ev['CATEGORIES'] . "'>&nbsp;</span>&nbsp;$sum</div>"; 
}

// http://doc.spip.org/@http_calendrier_avec_heure
function http_calendrier_avec_heure($evenement, $amj)
{
	$jour_debut = substr($evenement['DTSTART'], 0,8);
	$jour_fin = substr($evenement['DTEND'], 0, 8);
	if ($jour_fin <= 0) $jour_fin = $jour_debut;
	if (($jour_debut <= 0) OR ($jour_debut > $amj) OR ($jour_fin < $amj))
	  return "";
	
	$desc = propre($evenement['DESCRIPTION']);
	$sum = $evenement['SUMMARY'];
	$u = $GLOBALS['meta']['pcre_u'];
	$sum = typo($sum);
	if (!$sum) $sum = $desc;
	if ($lieu = $evenement['LOCATION'])
	  $sum .= '<br />' . $lieu;
	if ($perso = $evenement['ATTENDEE'])
	  $sum .=  '<br />' . substr($evenement['ATTENDEE'], 0,strpos($evenement['ATTENDEE'],'@'));
	if ($evenement['URL'])
	  $sum = http_href(quote_amp($evenement['URL']), $sum, $desc, 'border: 0px');

	$sum = pipeline('agenda_rendu_evenement',array('args'=>array('evenement'=>$evenement,'type'=>'avec_heure'),'data'=>$sum));
	$opacity = "";
	$deb_h = substr($evenement['DTSTART'],-6,2);
	$deb_m = substr($evenement['DTSTART'],-4,2);
	$fin_h = substr($evenement['DTEND'],-6,2);
	$fin_m = substr($evenement['DTEND'],-4,2);
	
	if ($deb_h >0 OR $deb_m > 0) {
	  if ((($deb_h > 0) OR ($deb_m > 0)) AND $amj == $jour_debut)
	    { $deb = "<span  style='font-weight: bold;'>" . $deb_h . ':' . $deb_m . '</span>';}
	  else { 
	    $deb = '...'; 
	  }
	  
	  if ((($fin_h > 0) OR ($fin_m > 0)) AND $amj == $jour_fin)
	    { $fin = "<span  style='font-weight: bold;'>" . $fin_h . ':' . $fin_m . '</span> ';}
	  else { 
	    $fin = '...'; 
	  }
	  
	  if ($amj == $jour_debut OR $amj == $jour_fin) {
	    $sum = "<div>$deb-$fin</div>$sum";
	  } else {
	    $opacity =' calendrier-opacity';
	  }
	}
	return "\n<div class='calendrier-arial10 calendrier-evenement " . $evenement['CATEGORIES'] ."$opacity'>$sum\n</div>\n"; 
}

// agenda mensuel 

// http://doc.spip.org/@http_calendrier_agenda
function http_calendrier_agenda ($annee, $mois, $jour_ved, $mois_ved, $annee_ved, $semaine = false,  $script='', $ancre='', $evt='') {

  if (!$script) $script =  $GLOBALS['PHP_SELF'] ;

  if (!$mois) {$mois = 12; $annee--;}
  elseif ($mois==13) {$mois = 1; $annee++;}
  if (!$evt) $evt = quete_calendrier_agenda($annee, $mois);
  $nom = affdate_mois_annee("$annee-$mois-1");
  return 
    "<div class='calendrier-titre calendrier-arial10'>" .
    calendrier_href($script, $annee, $mois, 1, 'mois', '', $ancre,'', $nom,'','',    $nom,'color: black;') .
    "<table width='100%' cellspacing='0' cellpadding='0'>" .
    http_calendrier_agenda_rv ($annee, $mois, $evt,				
			        'calendrier_href', $script, $ancre,
			        $jour_ved, $mois_ved, $annee_ved, 
				$semaine) . 
    "</table>" .
    "</div>";
}

// typographie un mois sous forme d'un tableau de 7 colonnes

// http://doc.spip.org/@http_calendrier_agenda_rv
function http_calendrier_agenda_rv ($annee, $mois, $les_rv, $fclic,
				    $script='', $ancre='',
				    $jour_ved='', $mois_ved='', $annee_ved='',
				    $semaine='') {
	global $spip_lang_left, $spip_lang_right;

	// Former une date correcte (par exemple: $mois=13; $annee=2003)
	$date_test = date("Y-m-d", mktime(0,0,0,$mois, 1, $annee));
	$mois = mois($date_test);
	$annee = annee($date_test);
	if ($semaine) 
	{
		$jour_semaine_valide = date("w",mktime(1,1,1,$mois_ved,$jour_ved,$annee_ved));
		if ($jour_semaine_valide==0) $jour_semaine_valide=7;
		$debut = mktime(1,1,1,$mois_ved,$jour_ved-$jour_semaine_valide+1,$annee_ved);
		$fin = mktime(1,1,1,$mois_ved,$jour_ved-$jour_semaine_valide+7,$annee_ved);
	} else { $debut = $fin = '';}
	
	$today=getdate(time());
	$jour_today = $today["mday"];
	$cemois = ($mois == $today["mon"] AND $annee ==  $today["year"]);

	$total = '';
	$ligne = '';
	$jour_semaine = date("w", mktime(1,1,1,$mois,1,$annee));
	if ($jour_semaine==0) $jour_semaine=7;
	for ($i=1;$i<$jour_semaine;$i++) $ligne .= "\n\t<td></td>";
	$classe0 = test_espace_prive() ? "" : " bordure_foncee";
	for ($j=1; (checkdate($mois,$j,$annee)); $j++) {
		$toile = "";
		$nom = mktime(1,1,1,$mois,$j,$annee);
		$jour_semaine = date("w",$nom);
		$title = "$j-$mois-$annee";
		if ($jour_semaine==0) $jour_semaine=7;

		if ($j == $jour_ved AND $mois == $mois_ved AND $annee == $annee_ved) {
		  $class= 'calendrier-arial11 calendrier-demiagenda';
		  $type = 'jour';
		  $couleur = "black";
		  } else if ($semaine AND $nom >= $debut AND $nom <= $fin) {
		  $class= 'calendrier-arial11 calendrier-demiagenda' . 
 		      (($jour_semaine==1) ? " calendrier-$spip_lang_left"  :
		       (($jour_semaine==7) ? " calendrier-$spip_lang_right" :
			''));
		  $type = ($semaine ? 'semaine' : 'jour') ;
		  $couleur = "black";
		} else {
		  if ($j == $jour_today AND $cemois) {
			$toile = 'jour_encours';
		    } else {
			if ($jour_semaine == 7) {
				$toile = "jour_dimanche";
			} else {
				$toile = 'jour_gris';
			}
				if (isset($les_rv[$j])) {
				  $toile = "jour_pris $toile";
				  $title = textebrut($les_rv[$j]['SUMMARY']);
				}
		  }
		  $class= 'calendrier-arial11 calendrier-agenda';
		  $type = ($semaine ? 'semaine' : 'jour') ;
		  $couleur = "black";
		}
		$ligne .= "\n\t<td><div class='$class$classe0 $toile'>" .
		  $fclic($script, $annee, $mois, $j,$type, '', $ancre,'', $title ,'','', $j, "color: $couleur; font-weight: bold") .
		  "</div></td>";
		if ($jour_semaine==7) 
		    {
		      if ($ligne) $total .= "\n<tr>$ligne\n</tr>";
		      $ligne = '';
		    }
	}
	return $total . (!$ligne ? '' : "\n<tr>$ligne\n</tr>");
}



// Fonctions pour la messagerie, la page d'accueil et les gadgets

// http://doc.spip.org/@http_calendrier_messages
function http_calendrier_messages($annee='', $mois='', $jour='', $heures='', $partie_cal='', $echelle='')
{
	$evtm = quete_calendrier_agenda($annee, $mois);
	if ($evtm OR !$heures)
		$evtm = http_calendrier_agenda($annee, $mois, $jour, $mois, $annee, false, generer_url_ecrire('calendrier'), '', $evtm);
	else $evtm= '';

	$evtt = http_calendrier_rv(quete_calendrier_taches_annonces(),"annonces")
	  . http_calendrier_rv(quete_calendrier_taches_pb(),"pb")
	  . http_calendrier_rv(quete_calendrier_taches_rv(), "rv");

	$evtr= '';
	if ($heures) {
		$date = date("$annee-$mois-$jour");
		$datef = "'$date $heures'";
		if ($heures = quete_calendrier_interval_rv("'$date'", $datef))
			$evtr = http_calendrier_ics_titre($annee,$mois,$jour,generer_url_ecrire('calendrier')) . http_calendrier_ics($annee, $mois, $jour, $echelle, $partie_cal, 90, array('', $heures));
	}
	return array($evtm, $evtt, $evtr);
}



// http://doc.spip.org/@http_calendrier_rv
function http_calendrier_rv($messages, $type) {

	$total = $date_rv = '';
	if (!$messages) return $total;
	$connect_quand = $GLOBALS['visiteur_session']['quand'];

	foreach ($messages as $row) {
		$rv = ($row['location'] == 'oui');
		$date = $row['dtstart'];
		$date_fin = $row['dtend'];
		if ($row['category']=="pb") $bouton = "pense-bete";
		else if ($row['category']=="affich") $bouton = "annonce";
		else $bouton = "message";

		if ($rv) {
			$date_jour = affdate_jourcourt($date);
			$total .= "<tr><td colspan='2'>" .
				(($date_jour == $date_rv) ? '' :
				"\n<div  class='calendrier-arial11'><b>$date_jour</b></div>") .
				"</td></tr>";
			$date_rv = $date_jour;
			$rv =
		((affdate($date) == affdate($date_fin)) ?
		 ("\n<div class='calendrier-arial9 fond-agenda'>"
		  . heures($date).":".minutes($date)."<br />"
		  . heures($date_fin).":".minutes($date_fin)."</div>") :
		( "\n<div class='calendrier-arial9 fond-agenda' style='text-align: center;'>"
		  . heures($date).":".minutes($date)."<br />...</div>" ));
		}

		$c = (strtotime($date) <= $connect_quand) ? '' : " color: red;";
		$total .= "<tr><td style='width: 24px' valign='middle'>" .
		  http_href($row['url'],
				     ($rv ?
				      http_img_pack("rv.gif", 'rv',
						    http_style_background($bouton . '.gif', "no-repeat;")) : 
				      http_img_pack($bouton.".gif", $bouton, "")),
				     '', '') .
		"</td>\n" .
		"<td valign='middle'><div style='font-weight: bold;$c'>" .
		$rv .
		http_href($row['url'], typo($row['summary']), '', '', 'calendrier-verdana10') .
		"</div></td></tr>\n";
	}

	if ($type == 'annonces') {
		$titre = _T('info_annonces_generales');
	}
	else if ($type == 'pb') {
		$titre = _T('infos_vos_pense_bete');
	}
	else if ($type == 'rv') {
		$titre = _T('info_vos_rendez_vous');
	}

	return
	  debut_cadre_enfonce("", true, "", $titre) .
	  "<table width='100%' border='0' cellpadding='0' cellspacing='2'>" .
	  $total .
	  "</table>" .
	  fin_cadre_enfonce(true);
}

// http://doc.spip.org/@http_calendrier_ics_message
function http_calendrier_ics_message($annee, $mois, $jour, $large)
{	

  if (!autoriser('ecrire')) return '';

  $b = _T("lien_nouvea_pense_bete");
  $v = _T("lien_nouveau_message");
  $j=  _T("lien_nouvelle_annonce");

  return "&nbsp;" .
    http_href(generer_action_auteur("editer_message","pb/$annee-$mois-$jour"), 
	       ($large ? $b : '&nbsp;'), 
	      $b,
	      'color: blue;',
	      'calendrier-arial10 pense-bete') .
    "\n" .
    http_href(generer_action_auteur("editer_message","normal/$annee-$mois-$jour"), 
	       ($large ? $v : '&nbsp;'), 
	      $v,
	      'color: green;',
	      'calendrier-arial10 message') .
    (($GLOBALS['connect_statut'] != "0minirezo") ? "" :
     ("\n" .
    http_href(generer_action_auteur("editer_message","affich/$annee-$mois-$jour"), 
		($large ? $j : '&nbsp;'), 
		$j,
		'color: #ff9900;',
		'calendrier-arial10 annonce')));
}

?>