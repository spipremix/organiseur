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

/**
 * Fonction vide pour charger ce fichier sans declencher de warning
 * @return void
 */
function organiseur_autoriser(){}

function autoriser_calendrier_bouton_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	if($GLOBALS['meta']['messagerie_agenda'] == 'oui')
		return true;
	return false;
}

function autoriser_messagerie_bouton_dist($faire, $type='', $id=0, $qui = NULL, $opt = NULL){
	if($GLOBALS['meta']['messagerie_agenda'] == 'oui')
		return true;
	return false;
}