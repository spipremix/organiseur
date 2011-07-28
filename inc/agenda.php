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


?>