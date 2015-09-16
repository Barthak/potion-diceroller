<?php
error_reporting(E_ALL);
/**
 * @author Fernando of Blood Sun Rising (http://www.bloodsunrising.com/)
 * @author Marten Koedam
 * @package Sheetgen
 * @subpackage Diceroller
 * @since 06-feb-2010
 * @license www.dalines.org/license
 * @copyright 2010, Dalines Sofware Library
 */
class WikiDiceRoller extends WikiPotion
{
	function _configure() {
		$conf = VoodooIni::load('sheetgen');
		$conf = $conf['diceroller'];
		
		$this->use_sheet_characters = $conf['use_sheet_characters'];
		$this->allow_any_character = $conf['allow_any_character'];
		$this->mutually_exclusive = $conf['mutually_exclusive'];
		$this->variable_difficulty = $conf['variable_difficulty'];
		$this->default_difficulty = $conf['default_difficulty'];
	}
	
	function init()
	{
		$this->_configure();
		
		$this->display = '';
		if($this->use_sheet_characters && !$this->allow_any_character && $_SESSION['user_id'] <= 0) {
			return $this->display .= VoodooError::displayError('No permission');
		}
		
		require_once(CLASSES.'TableFactory.php');
		$hash = isset($_GET['hash']) ? $_GET['hash'] : false;
		$limit = isset($this->args[0]) ? $this->args[0] : 12;
		
		if(!empty($_POST['name'])||!empty($_POST['character'])) {
			
			if(empty($_POST['action'])||empty($_POST['number'])) {
				$this->display .= VoodooError::displayError('Character, Action and Dice Pool are required fields');
			} else {
			
				$postvars = array(
					'character' => $_POST['name'] ? $_POST['name'] : $_POST['character'],
					'action' => $_POST['action']
				);
				
				$sgdr = new SheetgenDiceRoller($this->formatter->db, $postvars);
				$difficulty = ($this->variable_difficulty && isset($_POST['difficulty'])) ? $_POST['difficulty'] : $this->default_difficulty;
				if(!$sgdr->roll((int) $_POST['number'], $_POST['type'], isset($_POST['substract']), $difficulty)) {
					$this->display .= VoodooError::displayError('Incorrect usage of the Diceroller');
				} else {
					header(sprintf('Location: http://%s%s/wiki/%s?hash=%s', $_SERVER['SERVER_NAME'], PATH_TO_DOCROOT, $this->formatter->action, $sgdr->hash));
					exit();
				}
			}
		}
		
		$sgdr = new SheetgenDiceRoller($this->formatter->db);
		$t =& VoodooTemplate::getInstance();
		$old = $t->getDir();
		$t->setDir(SHEETGEN_TEMPLATES);
		
		if(!$hash) {
			$chars = false;
			
			$args = array('prepath'=>PATH_TO_DOCROOT, 'page'=>$this->formatter->action);
			$args['name'] = isset( $_POST['name'] ) ? $_POST['name'] : '';
			$args['action'] = isset( $_POST['action'] ) ? $_POST['action'] : '';
			$args['number'] = isset( $_POST['number'] ) ? $_POST['number'] : '';
			if($this->use_sheet_characters) {
				$chars = $sgdr->getCharacters($_SESSION['user_id']);
				if(sizeof($chars) == 0 && !$this->allow_any_character){
					$this->display .= VoodooError::displayError('No characters available, please create one first.');
					return;
				}
			}
			$chars && $args['use_sheet_characters'] = $chars;
			if($this->allow_any_character && !($this->mutually_exclusive && $chars)) {
				$args['allow_any_character'] = $this->allow_any_character;
			}
			$args['variable_difficulty'] = $this->variable_difficulty;
			$args['difficulty'] = isset( $_POST['difficulty'] ) ? $_POST['difficulty'] : $this->default_difficulty;
			$this->display .= $t->parse('diceroller',$args);

			
		}
		
		$q = $sgdr->getOverview($limit, $hash);
		require_once(CLASSES.'TableFactory.php');
		$tf = new TableFactory($q);
		$tf->setHiddenField(array('User', 'ROLL_ID', 'number', 'successes', 'roll_character', 'action', 'rolls', 'rerolls', 'difficulty', 'substract'));
		$tf->setValueProcessor(array('Result', 'Link', 'Roll Description'), array($this, 'tfValueProcessor'));
		$this->display .= $tf->getXHTMLTable('list report diceroller');
		$this->display .= sprintf('<a href="%s/wiki/%s">Refresh</a>', PATH_TO_DOCROOT, $this->formatter->action);
		
		$t->template_dir = $old;
	}
	
	function tfValueProcessor($args) {
		switch($args['head']) {
			case "Result":
				$r = $args['row'];
				$t =& VoodooTemplate::getInstance();
				return $t->parse('diceroller_result', $r);
			break;
			case "Roll Description":
				$r = $args['row'];
				$r['rerolls'] = (!$r['rerolls'])?'no rerolls':(($r['rerolls']==='10') ? '' : $r['rerolls'].'-again');
				$r['difficulty'] = ($r['difficulty']==$this->default_difficulty) ? '' : $r['difficulty'];
				$t =& VoodooTemplate::getInstance();
				return $t->parse('diceroller_description', $r);
			break;
			case "Link":
				return sprintf('<a href="%s/wiki/%s?hash=%s">Link</a>', PATH_TO_DOCROOT, $this->formatter->action, $args['value']);
			break;
		}
		return $args['value'];
	}
}

/**
 * 
 * @author Marten Koedam
 *
 */
class SheetgenDiceRoller extends AbstractObject {
	var $id;
	var $hash;
	var $action;
	var $number;
	var $successes;
	var $character;
	var $result;
	var $rerolls;
	var $substract;
	var $difficulty;
	var $user;
	var $created;
	/**
	 * (non-PHPdoc)
	 * @see AbstractObject#set($key)
	 */
	function set($id=null) {
		$id || $id = $this->id;
	}
	
	function getOverview($limit, $hash=false) {
		$sql = "SELECT CREATED as `Date/Time`, ROLL_ID, ROLL_NUMBER as number, ROLL_SUCCESSES as successes,
			ROLL_CHARACTER as roll_character, ROLL_ACTION as action, ROLL_RESULT as rolls,
			ROLL_REROLLS as rerolls, ROLL_SUBSTRACT as substract, ROLL_DIFFICULTY as difficulty,
			'' as `Roll Description`, '' as Result, USER_NAME as User, ROLL_HASH as Link 
			FROM TBL_SHEET_DICEROLLER as sd
			LEFT JOIN TBL_USER as u
				ON sd.USER_ID = u.USER_ID ";
		if($hash) {
			$sql .= " WHERE ROLL_HASH = ?? ";
		}
		$sql .= " ORDER BY CREATED DESC LIMIT 0, ".$limit;
		
		$q = $this->db->query($sql);
		$hash && $q->bind_values($hash);
		$q->execute();
		return $q;
	}
	/**
	 * 
	 */
	function getCharacters($user_id) {
		$sql = "SELECT NAME FROM TBL_SHEET_USER WHERE USER_ID = ?? ORDER BY NAME";
		$q = $this->db->query($sql);
		$q->bind_values($user_id);
		$q->execute();
		$rv = array();
		while($r = $q->fetch()) {
			$rv[] = $r->NAME;
		}
		return $rv;
	}
	/**
	 * (non-PHPdoc)
	 * @see AbstractObject#insert($graceful)
	 */
	function insert() {
		$this->created = date('Y-m-d H:i:s');
		$this->hash = $this->_uniqueHash();
		$this->user = ($_SESSION['user_id']>0) ? new User($this->db,$_SESSION['user_id']) : null;
		$this->character = htmlentities($this->character);
		$this->action = htmlentities($this->action);
		return parent::insert();
	}
	
	function _uniqueHash() {
		$hash = substr(md5(uniqid('diceroller')),0,8);
		$sql = "SELECT ROLL_HASH FROM TBL_SHEET_DICEROLLER WHERE ROLL_HASH = ??";
		$q = $this->db->query($sql);
		$q->bind_values($hash);
		$q->execute();
		if($q->rows())
			return $this->_uniqueHash();
		return $hash;
	}
	
	/**
	 * 
	 * @param int $number
	 * @param int $reroll
	 * @param boolean $substract
	 * @return boolean
	 */
	function roll($number, $reroll, $substract, $difficulty) {

		if(!$number)
			return false;
		if($number > 25)
			return false;
		
		$this->substract = $substract ? true : null;
		$this->difficulty = $difficulty;
		$this->rerolls = $reroll;
			
		$this->number = $number;
		$this->successes = 0;
		$result = array();
		$redo = 1;
 		$sw = 0;
		while($redo) {
			if($sw == 1)
				$number = $redo;
 			$sw || $sw = 1;
 			$redo = 0;
			for ($i = 0; $i < $number; $i++) {
			 	$value = rand(1, 10);
			 	if($value >= $difficulty) {
			 		$this->successes++;
				 	if($reroll && ($value >= $reroll)) {
	 					$redo++;
	 				}
	 				$value = sprintf('<span class="success">%s</span>', $value);
			 	} elseif($value == 1 && $substract) {
 					$this->successes--;
 					$value = sprintf('<span class="substracted">%s</span>', $value);
 				}
 				
 					
 				$result[] = $value;
			}
		}
		$this->result = implode(',', $result);
		$this->successes = ($this->successes >= 0) ? $this->successes : 0;
		
		return $this->insert();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see AbstractObject#_initObjectMaps()
	 */
	function _initObjectMaps() {
		$this->objectmap['dbtable'] = 'TBL_SHEET_DICEROLLER';
		$this->objectmap['primary']['id'] = 'ROLL_ID';
		$this->objectmap['properties']['hash'] = 'ROLL_HASH';
		$this->objectmap['properties']['number'] = 'ROLL_NUMBER';
		$this->objectmap['properties']['successes'] = 'ROLL_SUCCESSES';
		$this->objectmap['properties']['character'] = 'ROLL_CHARACTER';
		$this->objectmap['properties']['action'] = 'ROLL_ACTION';
		$this->objectmap['properties']['result'] = 'ROLL_RESULT';
		$this->objectmap['properties']['rerolls'] = 'ROLL_REROLLS';
		$this->objectmap['properties']['substract'] = 'ROLL_SUBSTRACT';
		$this->objectmap['properties']['difficulty'] = 'ROLL_DIFFICULTY';
		$this->objectmap['properties']['user->id'] = 'USER_ID';
		$this->objectmap['properties']['created'] = 'CREATED';
		
		$this->sqltypemap['id'] = 'INT(11) NOT NULL AUTO_INCREMENT';
		$this->sqltypemap['hash'] = 'CHAR(8) NOT NULL';
		$this->sqltypemap['number'] = 'INT(11) NOT NULL';
		$this->sqltypemap['successes'] = 'INT(11) NOT NULL';
		$this->sqltypemap['character'] = 'VARCHAR(255) NOT NULL';
		$this->sqltypemap['action'] = 'VARCHAR(255) NOT NULL';
		$this->sqltypemap['result'] = 'TEXT NOT NULL';
		$this->sqltypemap['rerolls'] = 'TINYINT(4) NOT NULL';
		$this->sqltypemap['substract'] = 'TINYINT(4) NOT NULL';
		$this->sqltypemap['difficulty'] = 'TINYINT(4) NOT NULL';
		$this->sqltypemap['user->id'] = 'INT(11) NULL';
		$this->sqltypemap['created'] = 'DATETIME NOT NULL';
		
		$this->sqlprops['unique'] = 'UNIQUE(`ROLL_HASH`)';
		$this->sqlprops['index'] = 'INDEX(`ROLL_HASH`)';
	}
}
/**
 * 
 * @author Marten Koedam
 *
 */
class WikiDiceRollerSetup extends WikiPotionSetup {
	/**
	 * (non-PHPdoc)
	 * @see classes/WikiPotionSetup#getTables()
	 */
	function getTables() {
		$objects = array('SheetgenDiceRoller' => array(SHEETGEN_SPELLBOOK,'Potions/WikiDiceRoller.php'));
		$rv = $this->setup->getCreateTablesFromAbstractObjects($objects);
		return $rv;
	}
}
/*
UPGRADE FROM 0.4 to 0.5

ALTER TABLE `TBL_SHEET_DICEROLLER` CHANGE COLUMN `ROLL_RESULT` `ROLL_RESULT` TEXT;
ALTER TABLE `TBL_SHEET_DICEROLLER` ADD COLUMN `ROLL_REROLLS` TINYINT(4) NULL;
ALTER TABLE `TBL_SHEET_DICEROLLER` ADD COLUMN `ROLL_SUBSTRACT` TINYINT(4) NULL;
ALTER TABLE `TBL_SHEET_DICEROLLER` ADD COLUMN `ROLL_DIFFICULTY` TINYINT(4) NULL;

 */
?>