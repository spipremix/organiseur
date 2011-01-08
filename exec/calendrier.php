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

include_spip('inc/presentation');
include_spip('inc/agenda');

// http://doc.spip.org/@exec_calendrier_dist
function exec_calendrier_dist()
{
	if ($date = _request('date')){
		$time = explode('-', $date);
		if (count($time) <= 2) $time[]=1;
		if (count($time) <= 2) $time[]=1;
		$time = array_reverse($time);
	} else {
		$time = array(_request('jour'), _request('mois'), _request('annee'));
		if ($time[1] AND $time[0] AND $time[2])
			$date = mktime(1,1,1, $time[1], $time[0], $time[2]);
		else 	$date = date("Y-m-d", $timem = time()); 
	}

	$type = _request('type');

	if ($type == 'semaine') {

		$GLOBALS['afficher_bandeau_calendrier_semaine'] = true;

		$titre = _T('titre_page_calendrier',
		    array('nom_mois' => nom_mois($date), 'annee' => annee($date)));
	  }
	elseif ($type == 'jour') {
		$titre = nom_jour($date)." ". affdate_jourcourt($date);
	}  else {
		$titre = _T('titre_page_calendrier',
		    array('nom_mois' => nom_mois($date), 'annee' => annee($date)));
		$type = 'mois';
	}
	$ancre = 'calendrier-1';
	$r = generer_url_ecrire('calendrier', "type=$type") . "#$ancre";
	$r = http_calendrier_init($time, $type, _request('echelle'), _request('partie_cal'), $r);

	if (_AJAX) {
		ajax_retour($r);
	} else {
		$commencer_page = charger_fonction('commencer_page', 'inc');
		echo $commencer_page($titre, "accueil", "calendrier");
// ne produit rien par defaut, mais est utilisee par le plugin agenda
		echo barre_onglets("calendrier", "calendrier"); 
		echo debut_grand_cadre(true);
		echo "\n<div>&nbsp;</div>\n<div id='", $ancre, "'>",$r,'</div>';
		echo fin_grand_cadre(true);
		echo fin_page();
	}
}

?>
