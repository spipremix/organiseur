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

function convert_dateical($dateical){
	$d = explode('T',$dateical);
	$amj = reset($d);
	$s = substr($amj,0,4).'-'.substr($amj,4,2).'-'.substr($amj,6,2);
	if (count($d)>1){
		$his = end($d);
		$s .= ' '.substr($his,0,2).":".substr($his,2,2).":".substr($his,4,2);
	}
	return $s;
}
function action_quete_calendrier_dist(){
	$securiser_action = charger_fonction('securiser_action','inc');
	$securiser_action();

	$start = _request('start');
	$end = _request('end');

	include_spip('inc/quete_calendrier');

	// recuperer la liste des evenements au format ics
	$limites = array(sql_quote(date('Y-m-d H:i:s',$start)),sql_quote(date('Y-m-d H:i:s',$end)));
	list($entier,$duree) = quete_calendrier_interval($limites);

	// la retransformer au format attendu par fullcalendar
	$evt = array();
	// facile : chaque evt n'est mentionne qu'une fois, a une date
	foreach($entier as $amj=>$l){
		$date = substr($amj,0,4).'-'.substr($amj,4,2).'-'.substr($amj,6,2);
		foreach($l as $e){
			$evt[] = array(
				'id' => 0,
				'title' => $e['SUMMARY'],
				'allDay' => true,
				'start' => $date,
				'end' => $date,
				'url' => str_replace('&amp;','&',$e['URL']),
				'className' => "calendrier-event ".$e['CATEGORIES'],
				'description' => $e['DESCRIPTION'],
			);
		}
	}
	// ici il faut faire attention : un evt apparait N fois
	// mais on a son id
	$seen = array();
	foreach($duree as $amj=>$l){
		foreach($l as $id=>$e){
			if (!isset($seen[$e['URL']])){
				$evt[] = array(
					'id' => $id,
					'title' => $e['SUMMARY'],
					'allDay' => false,
					'start' => convert_dateical($e['DTSTART']), //Ymd\THis
					'end' => convert_dateical($e['DTEND']), // Ymd\THis
					'url' => str_replace('&amp;','&',$e['URL']),
					'className' => "calendrier-event ".$e['CATEGORIES'],
					'description' => $e['DESCRIPTION'],
				);
				$seen[$e['URL']] = true;
			}
		}
	}

	include_spip('inc/json');
	echo json_encode($evt);
}