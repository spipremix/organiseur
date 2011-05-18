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
 * Declaration des champs complémentaires sur la table auteurs, pour les clients
 *
 * @param  $tables
 * @return
 */
function organiseur_declarer_tables_objets_sql($tables){

	$tables['spip_auteurs']['field']["imessage"] = "VARCHAR(3)";
	$tables['spip_auteurs']['field']["messagerie"] = "VARCHAR(3)";
	return $tables;
	
}


/**
 * Interfaces des tables agenda et messagerie
 *
 * @param array $interfaces
 * @return array
 */
function organiseur_declarer_tables_interfaces($interfaces){
	$interfaces['table_des_tables']['messages']='messages';
	$interfaces['table_titre']['messages']= "titre, '' AS lang";
	$interfaces['table_date']['messages'] = 'date_heure';

	return $interfaces;
}

/**
 * Table principale messagerie
 *
 * @param array $tables_principales
 * @return array
 */
function organiseur_declarer_tables_principales($tables_principales){

	$spip_messages = array(
			"id_message"	=> "bigint(21) NOT NULL",
			"titre"	=> "text DEFAULT '' NOT NULL",
			"texte"	=> "longtext DEFAULT '' NOT NULL",
			"type"	=> "varchar(6) DEFAULT '' NOT NULL",
			"date_heure"	=> "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
			"date_fin"	=> "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
			"rv"	=> "varchar(3) DEFAULT '' NOT NULL",
			"statut"	=> "varchar(6)  DEFAULT '0' NOT NULL",
			"id_auteur"	=> "bigint(21) NOT NULL",
			"maj"	=> "TIMESTAMP");

	$spip_messages_key = array(
			"PRIMARY KEY"	=> "id_message",
			"KEY id_auteur"	=> "id_auteur");

	$tables_principales['spip_messages'] =
		array('field' => &$spip_messages, 'key' => &$spip_messages_key);

	return $tables_principales;
}

?>
