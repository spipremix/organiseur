<?php

/**
 * Déclarations des configurations qui peuvent être sauvegardées
 *
 * @package SPIP\Organiseur\Pipelines
 **/

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Ajoute les metas sauvegardables d'Organiseur pour le plugin IEConfig
 *
 * @pipeline ieconfig_metas
 *
 * @param array $table
 *     Déclaration des sauvegardes
 * @return array
 *     Déclaration des sauvegardes complétées
 **/
function organiseur_ieconfig_metas($table) {
	$table['organiseur_meta']['titre'] = _T('titre_messagerie_agenda');
	$table['organiseur_meta']['icone'] = 'messagerie-16.png';
	$table['organiseur_meta']['metas_brutes'] = 'messagerie_agenda';

	return $table;
}
