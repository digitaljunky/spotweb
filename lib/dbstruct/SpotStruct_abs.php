<?php
abstract class SpotStruct_abs {
	protected $_spotdb;
	protected $_dbcon;

	public function __construct(SpotDb $spotdb) {
		$this->_spotdb = $spotdb;
		$this->_dbcon = $spotdb->getDbHandle();
	} # __construct

	/*
	 * optimaliseer/analyseer een aantal tables welke veel veranderen,
	 * deze functie wijzigt geen data!
	 */
	abstract function analyze();

	/* converteert een "spotweb" datatype naar een mysql datatype */
	abstract function swDtToNative($colType);

	/* converteert een mysql datatype naar een "spotweb" datatype */
	abstract function nativeDtToSw($colInfo);
	
	/*
	 * Add an index, kijkt eerst wel of deze index al bestaat,
	 * $idxType kan danwel 'UNIQUE' danwel 'FULLTEXT' zijn
	 */
	abstract function addIndex($idxname, $idxType, $tablename, $colList);

	/* dropt een index als deze bestaat */
	abstract function dropIndex($idxname, $tablename);

	/* voegt een column toe, kijkt wel eerst of deze nog niet bestaat */
	abstract function addColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);

	/* wijzigt een column - controleert *niet* of deze voldoet aan het prototype */
	abstract function modifyColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation, $what);

	/* dropt een kolom (mits db dit ondersteunt) */
	abstract function dropColumn($colName, $tablename);

	/* controleert of een index bestaat */
	abstract function indexExists($idxname, $tablename);

	/* controleert of een kolom bestaat */
	abstract function columnExists($tablename, $colname);

	/* controleert of een tabel bestaat */
	abstract function tableExists($tablename);
	
	/* controleert of een full text index bestaat */
	abstract function ftsExists($ftsname, $tablename, $colList);
	
	/* maakt een full text index aan */
	abstract function createFts($ftsname, $tablename, $colList);
	
	/* dropt en fulltext index */
	abstract function dropFts($ftsname, $tablename, $colList);
	
	/* geeft FTS info terug */
	abstract function getFtsInfo($ftsname, $tablename, $colList);
	
	/* ceeert een lege tabel met enkel een ID veld, collation kan UTF8 of ASCII zijn */
	abstract function createTable($tablename, $collation);

	/* creeert een foreign key constraint */
	abstract function addForeignKey($tablename, $colname, $reftable, $refcolumn, $action);
	
	/* dropped een foreign key constraint */
	abstract function dropForeignKey($tablename, $colname, $reftable, $refcolumn, $action);

	/* verandert een storage engine (concept dat enkel mysql kent :P ) */
	abstract function alterStorageEngine($tablename, $engine);

	/* drop een table */
	abstract function dropTable($tablename);
	
	/* rename een table */
	abstract function renameTable($tablename, $newTableName);

	/* Geeft, in een afgesproken formaat, de index informatie terug */
	abstract function getIndexInfo($idxname, $tablename);
	
	/* Geeft, in een afgesproken formaat, de index formatie terug */
	abstract function getColumnInfo($tablename, $colname);
	
	/* controleert of de index structuur hetzelfde is als de gewenste, zo niet, maak hem opnieuw aan */
	function validateIndex($idxname, $type, $tablename, $colList) {
		echo "\tValidating index " . $idxname . PHP_EOL;
		
		if (!$this->compareIndex($idxname, $type, $tablename, $colList)) {
			# Drop de index
			if ($this->indexExists($idxname, $tablename)) {
				echo "\t\tDropping index " . $idxname . PHP_EOL;
				$this->dropIndex($idxname, $tablename);
			} # if
			
			echo "\t\tAdding index " . $idxname . PHP_EOL;
			
			# en creeer hem opnieuw
			$this->addIndex($idxname, $type, $tablename, $colList);
		} # if
	} # validateIndex

	/* controleert of de fulltext structuur hetzelfde is als de gewenste, zo niet, maak hem opnieuw aan */
	function validateFts($ftsname, $tablename, $colList) {
		echo "\tValidating FTS " . $ftsname . PHP_EOL;
		
		if (!$this->compareFts($ftsname, $tablename, $colList)) {
			# Drop de FTS
			if ($this->ftsExists($ftsname, $tablename, $colList)) {
				echo "\t\tDropping FTS " . $ftsname . PHP_EOL;
				$this->dropFts($ftsname, $tablename, $colList);
			} # if
			
			echo "\t\tAdding FTS " . $ftsname . PHP_EOL;
			
			# en creeer hem opnieuw
			$this->createFts($ftsname, $tablename, $colList);
		} # if
	} # validateFts

	/* controleert of de index structuur hetzelfde is als de gewenste, zo niet, maak hem opnieuw aan */
	function validateColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation) {
		echo "\tValidating " . $tablename . "(" . $colName . ")" . PHP_EOL;

		$compResult = $this->compareColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);
		if ($compResult !== true) {
			if ($this->columnExists($tablename, $colName)) {
				echo "\t\tModifying column " . $colName . " (" . $compResult . ") on " . $tablename . PHP_EOL;
				$this->modifyColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation, $compResult);
			} else {
				echo "\t\tAdding column " . $colName . "(" . $colType . ") to " . $tablename . PHP_EOL;
				$this->addColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);
			} # else
		} # if
	} # validateColumn
	
	/* vergelijkt een column met de gewenste structuur */
	function compareColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation) {
		# Vraag nu de column informatie op
		$q = $this->getColumnInfo($tablename, $colName);
		
		# Als de column helemaal niet gevonden wordt..
		if (empty($q)) {
			return false;
		} # if
		
		# controleer het type
		if (strtolower($q['COLUMN_TYPE']) != strtolower($this->swDtToNative($colType))) {
			#var_dump($q);
			#var_dump($colType);
			#var_dump($this->swDtToNative($colType));
			#die();
			return 'type';
		} # if

		# controleer default
		if (strtolower($q['COLUMN_DEFAULT']) != strtolower($colDefault)) {
			return 'default';
		} # if

		# controleer NOT NULL setting
		if (strtolower($q['NOTNULL']) != $notNull) {
			return 'not null';
		} # if

		# controleer NOT NULL setting
		if ((strtolower($q['CHARACTER_SET_NAME']) != $collation) && ($q['CHARACTER_SET_NAME'] != null)) {
			return 'charset';
		} # if
		
		return true;
	} # compareColumn

	
	/* vergelijkt een index met de gewenste structuur */
	function compareIndex($idxname, $type, $tablename, $colList) {
		# Vraag nu de index informatie op
		$q = $this->getIndexInfo($idxname, $tablename);
		
		# Als het aantal kolommen niet gelijk is
		if (count($q) != count($colList)) {
			return false;
		} # if
		
		# we loopen vervolgens door elke index kolom heen, en vergelijken
		# dan of ze in dezelfde volgorde staan en dezelfde eigenschappen hebben
		for($i = 0; $i < count($colList); $i++) {
			$same = true;
			
			if ($colList[$i] != $q[$i]['column_name']) {
				$same = false;
			} # if

			if ($same) {
				switch(strtolower($type)) {
					case 'fulltext'		: $same = (strtolower($q[$i]['index_type']) == 'fulltext'); break;
					case 'unique'		: $same = ($q[$i]['non_unique'] == 0); break;
					case ''				: $same = (strtolower($q[$i]['index_type']) != 'fulltext') && ($q[$i]['non_unique'] == 1);
				} # switch
			} # if
			
			if (!$same) {
				#var_dump($q[$i]);
				#var_dump($type);
				#var_dump($colList);
				#die();
				return false;
			} # if
		} # for
		
		return true;
	} # compareIndex
	
	/* vergelijkt een FTS met de gewenste structuur */
	function compareFts($ftsname, $tablename, $colList) {
		# Vraag nu de FTS informatie op
		$q = $this->getFtsInfo($ftsname, $tablename, $colList);
		
		# Als het aantal kolommen niet gelijk is
		if (count($q) != count($colList)) {
			return false;
		} # if

		# we loopen vervolgens door elke index kolom heen, en vergelijken
		# dan of ze in dezelfde volgorde staan en dezelfde eigenschappen hebben
		for($i = 0; $i < count($colList); $i++) {
			if ($colList[$i + 1] != $q[$i]['column_name']) {
				return false;
			} # if
		} # for
		
		return true;
	} # compareFts

	function updateSchema() {
		# oude cache droppen, converteren gaat te vaak fout
		if (($this->_spotdb->getSchemaVer() < 0.50) && ($this->tableExists('cache'))) {
			$this->dropTable('cache');
		} # if
		if (($this->_spotdb->getSchemaVer() < 0.51) && ($this->tableExists('cache')) && (!$this->tableExists('cachetmp')) && ($this instanceof SpotStruct_mysql)) { 
			$this->renameTable('cache', 'cachetmp');
		} # if

		# drop eventueel FTS indexes op de spotsfull tabel
		$this->dropIndex("idx_spotsfull_fts_1", "spotsfull");
		$this->dropIndex("idx_spotsfull_fts_2", "spotsfull");
		$this->dropIndex("idx_spotsfull_fts_3", "spotsfull");
		$this->dropIndex("idx_spotsfull_2", "spotsfull"); # Index on userid
		$this->dropIndex("idx_nntp_2", "nntp");
		$this->dropIndex("idx_nntp_3", "nntp");

		# relaties wissen
		$this->dropForeignKey('spotsfull', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('spotstatelist', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('commentsposted', 'inreplyto', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('commentsposted', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('commentsxover', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('commentsfull', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('reportsposted', 'inreplyto', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->dropForeignKey('reportsposted', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		
		##############################################################################################
		# Opschonen data #############################################################################
		##############################################################################################
		if (($this instanceof SpotStruct_mysql) && (false)) {
			echo "Cleaning up old data..." . PHP_EOL;
			if ($this->tableExists('usersettings') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE usersettings FROM usersettings LEFT JOIN users ON usersettings.userid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('sessions') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE sessions FROM sessions LEFT JOIN users ON sessions.userid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('spotstatelist') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE spotstatelist FROM spotstatelist LEFT JOIN users ON spotstatelist.ouruserid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('usergroups') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE usergroups FROM usergroups LEFT JOIN users ON usergroups.userid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('usergroups') && $this->tableExists('securitygroups')) {
				$this->_dbcon->rawExec("DELETE usergroups FROM usergroups LEFT JOIN securitygroups ON usergroups.groupid=securitygroups.id WHERE securitygroups.id IS NULL");
			} # if
			if ($this->tableExists('grouppermissions') && $this->tableExists('securitygroups')) {
				$this->_dbcon->rawExec("DELETE grouppermissions FROM grouppermissions LEFT JOIN securitygroups ON grouppermissions.groupid=securitygroups.id WHERE securitygroups.id IS NULL");
			} # if
			if ($this->tableExists('commentsposted') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE commentsposted FROM commentsposted LEFT JOIN users ON commentsposted.ouruserid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('commentsposted') && $this->tableExists('spots')) {
				$this->_dbcon->rawExec("DELETE commentsposted FROM commentsposted LEFT JOIN spots ON commentsposted.inreplyto=spots.messageid WHERE spots.messageid IS NULL");
			} # if
			if ($this->tableExists('commentsfull') && $this->tableExists('commentsxover')) {
				$this->_dbcon->rawExec("DELETE commentsfull FROM commentsfull LEFT JOIN commentsxover ON commentsfull.messageid=commentsxover.messageid WHERE commentsxover.messageid IS NULL");
			} # if
			if ($this->tableExists('spotsfull') && $this->tableExists('spots')) {
				$this->_dbcon->rawExec("DELETE spotsfull FROM spotsfull LEFT JOIN spots ON spotsfull.messageid=spots.messageid WHERE spots.messageid IS NULL");
			} # if
			if ($this->tableExists('spotstatelist') && $this->tableExists('spots')) {
				$this->_dbcon->rawExec("DELETE spotstatelist FROM spotstatelist LEFT JOIN spots ON spotstatelist.messageid=spots.messageid WHERE spots.messageid IS NULL");
			} # if
			if ($this->tableExists('reportsposted') && $this->tableExists('users')) {
				$this->_dbcon->rawExec("DELETE reportsposted FROM reportsposted LEFT JOIN users ON reportsposted.ouruserid=users.id WHERE users.id IS NULL");
			} # if
			if ($this->tableExists('reportsposted') && $this->tableExists('spots')) {
				$this->_dbcon->rawExec("DELETE reportsposted FROM reportsposted LEFT JOIN spots ON reportsposted.inreplyto=spots.messageid WHERE spots.messageid IS NULL");
			} # if
		} # if
		
		# ---- spots table ---- #
		$this->createTable('spots', "utf8"); 
		$this->validateColumn('messageid', 'spots', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('poster', 'spots', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('title', 'spots', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('tag', 'spots', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('category', 'spots', 'INTEGER', NULL, false, '');
		$this->validateColumn('subcata', 'spots', 'VARCHAR(64)', NULL, false, 'ascii'); 
		$this->validateColumn('subcatb', 'spots', 'VARCHAR(64)', NULL, false, 'ascii'); 
		$this->validateColumn('subcatc', 'spots', 'VARCHAR(64)', NULL, false, 'ascii'); 
		$this->validateColumn('subcatd', 'spots', 'VARCHAR(64)', NULL, false, 'ascii'); 
		$this->validateColumn('subcatz', 'spots', 'VARCHAR(64)', NULL, false, 'ascii'); 
		$this->validateColumn('stamp', 'spots', 'UNSIGNED INTEGER', NULL, false, '');
		$this->validateColumn('reversestamp', 'spots', 'INTEGER', "0", false, '');
		$this->validateColumn('filesize', 'spots', 'UNSIGNED BIGINTEGER', "0", true, '');
		$this->validateColumn('moderated', 'spots', 'BOOLEAN', NULL, false, '');
		$this->validateColumn('commentcount', 'spots', 'INTEGER', "0", false, '');
		$this->validateColumn('spotrating', 'spots', 'INTEGER', "0", false, '');
		$this->validateColumn('reportcount', 'spots', 'INTEGER', "0", false, '');
		$this->validateColumn('spotterid', 'spots', 'VARCHAR(32)', NULL, false, 'ascii'); 
		$this->alterStorageEngine("spots", "MyISAM");
		
		# ---- spotsfull table ---- #
		$this->createTable('spotsfull', "utf8"); 
		$this->validateColumn('messageid', 'spotsfull', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('verified', 'spotsfull', 'BOOLEAN', NULL, false, '');
		$this->validateColumn('usersignature', 'spotsfull', 'VARCHAR(255)', NULL, false, 'ascii'); 
		$this->validateColumn('userkey', 'spotsfull', 'VARCHAR(512)', NULL, false, 'ascii'); 
		$this->validateColumn('xmlsignature', 'spotsfull', 'VARCHAR(255)', NULL, false, 'ascii'); 
		$this->validateColumn('fullxml', 'spotsfull', 'TEXT', NULL, false, 'utf8');
		$this->alterStorageEngine("spotsfull", "InnoDB");
	
		# ---- nntp table ---- #
		$this->createTable('nntp', "utf8"); 
		$this->validateColumn('server', 'nntp', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('maxarticleid', 'nntp', 'INTEGER', NULL, false, '');
		$this->validateColumn('nowrunning', 'nntp', 'INTEGER', "0", false, '');
		$this->validateColumn('lastrun', 'nntp', 'INTEGER', "0", false, '');
		$this->validateColumn('serverdatelastrun', 'nntp', 'VARCHAR(14)', "00000000000000", false, 'ascii');
		$this->alterStorageEngine("nntp", "InnoDB");
		
		# ---- commentsxover table ---- #
		$this->createTable('commentsxover', "ascii"); 
		$this->validateColumn('messageid', 'commentsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('nntpref', 'commentsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('spotrating', 'commentsxover', 'INTEGER', "0", false, '');
		$this->alterStorageEngine("commentsxover", "InnoDB");

		# ---- reportsxover table ---- #
		$this->createTable('reportsxover', "ascii"); 
		$this->validateColumn('messageid', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('fromhdr', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('keyword', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('nntpref', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
		$this->alterStorageEngine("reportsxover", "InnoDB");
		
		# ---- spotstatelist table ---- #
		$this->createTable('spotstatelist', "ascii"); 
		$this->validateColumn('messageid', 'spotstatelist', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('ouruserid', 'spotstatelist', 'INTEGER', "0", false, '');
		$this->validateColumn('download', 'spotstatelist', 'INTEGER', NULL, false, '');
		$this->validateColumn('watch', 'spotstatelist', 'INTEGER', NULL, false, '');
		$this->validateColumn('seen', 'spotstatelist', 'INTEGER', NULL, false, '');
		$this->alterStorageEngine("spotstatelist", "InnoDB");
		
		# ---- commentsfull table ---- #
		$this->createTable('commentsfull', "ascii"); 
		$this->validateColumn('messageid', 'commentsfull', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('fromhdr', 'commentsfull', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('stamp', 'commentsfull', 'INTEGER', NULL, false, '');
		$this->validateColumn('usersignature', 'commentsfull', 'VARCHAR(255)', NULL, false, 'ascii'); 
		$this->validateColumn('userkey', 'commentsfull', 'VARCHAR(512)', NULL, false, 'ascii'); 
		$this->validateColumn('spotterid', 'commentsfull', 'VARCHAR(32)', NULL, false, 'ascii'); 
		$this->validateColumn('hashcash', 'commentsfull', 'VARCHAR(255)', NULL, false, 'ascii'); 
		$this->validateColumn('body', 'commentsfull', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('verified', 'commentsfull', 'BOOLEAN', NULL, false, '');
		$this->validateColumn('avatar', 'commentsfull', 'TEXT', NULL, false, 'ascii');
		$this->alterStorageEngine("commentsfull", "InnoDB");
											
		# ---- settings table ---- #
		$this->createTable('settings', "ascii"); 
		$this->validateColumn('name', 'settings', 'VARCHAR(128)', "''", true, 'ascii'); 
		$this->validateColumn('value', 'settings', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('serialized', 'settings', 'boolean', NULL, false, '');
		$this->alterStorageEngine("settings", "InnoDB");

		# ---- commentsposted table ---- #
		$this->createTable('commentsposted', "ascii"); 
		$this->validateColumn('ouruserid', 'commentsposted', 'INTEGER', "0", true, '');
		$this->validateColumn('messageid', 'commentsposted', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('inreplyto', 'commentsposted', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('randompart', 'commentsposted', 'VARCHAR(32)', "''", true, 'ascii');
		$this->validateColumn('rating', 'commentsposted', 'INTEGER', 0, true, '');
		$this->validateColumn('body', 'commentsposted', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('stamp', 'commentsposted', 'INTEGER', "0", true, '');
		$this->alterStorageEngine("commentsposted", "InnoDB");

		# ---- spotsposted table ---- #
		$this->createTable('spotsposted', "utf8"); 
		$this->validateColumn('messageid', 'spotsposted', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('ouruserid', 'spotsposted', 'INTEGER', "0", true, '');
		$this->validateColumn('stamp', 'spotsposted', 'UNSIGNED INTEGER', NULL, false, '');
		$this->validateColumn('title', 'spotsposted', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('tag', 'spotsposted', 'VARCHAR(128)', NULL, false, 'utf8');
		$this->validateColumn('category', 'spotsposted', 'INTEGER', NULL, false, '');
		$this->validateColumn('subcats', 'spotsposted', 'VARCHAR(255)', NULL, false, 'ascii'); 
		$this->validateColumn('filesize', 'spotsposted', 'UNSIGNED BIGINTEGER', "0", true, '');
		$this->validateColumn('fullxml', 'spotsposted', 'TEXT', NULL, false, 'utf8');
		$this->alterStorageEngine("spotsposted", "InnoDB");
		
		# ---- reportsposted table ---- #
		$this->createTable('reportsposted', "ascii"); 
		$this->validateColumn('ouruserid', 'reportsposted', 'INTEGER', "0", true, '');
		$this->validateColumn('messageid', 'reportsposted', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('inreplyto', 'reportsposted', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('randompart', 'reportsposted', 'VARCHAR(32)', "''", true, 'ascii');
		$this->validateColumn('body', 'reportsposted', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('stamp', 'reportsposted', 'INTEGER', "0", true, '');
		$this->alterStorageEngine("reportsposted", "InnoDB");
		
		# ---- usersettings table ---- #
		$this->createTable('usersettings', "utf8"); 
		$this->validateColumn('userid', 'usersettings', 'INTEGER', '0', true, '');
		$this->validateColumn('privatekey', 'usersettings', "TEXT", NULL, false, 'ascii');
		$this->validateColumn('publickey', 'usersettings', "TEXT", NULL, false, 'ascii');
		$this->validateColumn('avatar', 'usersettings', "TEXT", NULL, false, 'ascii');
		$this->validateColumn('otherprefs', 'usersettings', "TEXT", NULL, false, 'utf8');
		$this->alterStorageEngine("usersettings", "InnoDB");
	
		# ---- users table ---- #
		$this->createTable('users', "utf8"); 
		$this->validateColumn('username', 'users', "VARCHAR(128)", "''", true, 'utf8');
		$this->validateColumn('firstname', 'users', "VARCHAR(128)", "''", true, 'utf8');
		$this->validateColumn('passhash', 'users', "VARCHAR(40)", "''", true, 'ascii');
		$this->validateColumn('lastname', 'users', "VARCHAR(128)", "''", true, 'utf8');
		$this->validateColumn('mail', 'users', "VARCHAR(128)", "''", true, 'utf8');
		$this->validateColumn('apikey', 'users', "VARCHAR(32)", "''", true, 'ascii');
		$this->validateColumn('lastlogin', 'users', "INTEGER", "0", true, '');
		$this->validateColumn('lastvisit', 'users', "INTEGER", "0", true, '');
		$this->validateColumn('lastread', 'users', "INTEGER", "0", true, '');
		$this->validateColumn('lastapiusage', 'users', "INTEGER", "0", true, '');
		$this->validateColumn('deleted', 'users', "BOOLEAN", 'false', true, '');
		$this->alterStorageEngine("users", "InnoDB");

		# ---- sessions ---- #
		$this->createTable('sessions', "ascii");
		$this->validateColumn('sessionid', 'sessions', 'VARCHAR(128)', NULL, false, 'ascii');
		$this->validateColumn('userid', 'sessions', 'INTEGER', NULL, false, '');
		$this->validateColumn('hitcount', 'sessions', 'INTEGER', NULL, false, '');
		$this->validateColumn('lasthit', 'sessions', 'INTEGER', NULL, false, '');
		$this->alterStorageEngine("sessions", "InnoDB");

		# ---- securitygroups ----
		$this->createTable('securitygroups', "ascii"); 
		$this->validateColumn('name', 'securitygroups', 'VARCHAR(128)', NULL, false, 'ascii');
		$this->alterStorageEngine("securitygroups", "InnoDB");

		# ---- grouppermissions ----
		$this->createTable('grouppermissions', "ascii"); 
		$this->validateColumn('groupid', 'grouppermissions', 'INTEGER', "0", true, '');
		$this->validateColumn('permissionid', 'grouppermissions', 'INTEGER', "0", true, '');
		$this->validateColumn('objectid', 'grouppermissions', "VARCHAR(128)", "''", true, 'ascii');
		$this->validateColumn('deny', 'grouppermissions', "BOOLEAN", 'false', true, ''); 
		$this->alterStorageEngine("grouppermissions", "InnoDB");
		
		# ---- usergroups ----
		$this->createTable('usergroups', "ascii"); 
		$this->validateColumn('userid', 'usergroups', 'INTEGER', "0", true, '');
		$this->validateColumn('groupid', 'usergroups', 'INTEGER', "0", true, '');
		$this->validateColumn('prio', 'usergroups', 'INTEGER', '1', true, '');
		$this->alterStorageEngine("usergroups", "InnoDB");
		
		# ---- notifications ----
		$this->createTable('notifications', "ascii"); 
		$this->validateColumn('userid', 'notifications', 'INTEGER', "0", true, '');
		$this->validateColumn('stamp', 'notifications', 'INTEGER', "0", true, '');
		$this->validateColumn('objectid', 'notifications', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('type', 'notifications', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('title', 'notifications', 'VARCHAR(128)', "''", true, 'utf8');
		$this->validateColumn('body', 'notifications', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('sent', 'notifications', 'BOOLEAN', 'false', true, ''); 
		$this->alterStorageEngine("notifications", "InnoDB");

		# ---- filters ----
		$this->createTable('filters', "utf8"); 
		$this->validateColumn('userid', 'filters', 'INTEGER', "0", true, '');
		$this->validateColumn('filtertype', 'filters', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('title', 'filters', 'VARCHAR(128)', "''", true, 'utf8');
		$this->validateColumn('icon', 'filters', 'VARCHAR(128)', "''", true, 'utf8');
		$this->validateColumn('torder', 'filters', 'INTEGER', "0", true, '');
		$this->validateColumn('tparent', 'filters', 'INTEGER', "0", true, '');
		$this->validateColumn('tree', 'filters', 'TEXT', NULL, false, 'ascii');
		$this->validateColumn('valuelist', 'filters', 'TEXT', NULL, false, 'utf8');
		$this->validateColumn('sorton', 'filters', 'VARCHAR(128)', NULL, false, 'ascii');
		$this->validateColumn('sortorder', 'filters', 'VARCHAR(128)', NULL, false, 'ascii');
		$this->alterStorageEngine("filters", "InnoDB");

		# ---- spotteridblacklist table ---- #
		$this->createTable('spotteridblacklist', "utf8");
		$this->validateColumn('spotterid', 'spotteridblacklist', 'VARCHAR(32)', NULL, false, 'ascii');
		$this->validateColumn('ouruserid', 'spotteridblacklist', 'INTEGER', "0", true, '');
		$this->validateColumn('origin', 'spotteridblacklist', 'VARCHAR(255)', NULL, false, 'ascii');
		$this->alterStorageEngine("spotteridblacklist", "InnoDB");

		# ---- cache table ---- #
		$this->createTable('cache', "ascii");
		$this->validateColumn('resourceid', 'cache', 'VARCHAR(128)', "''", true, 'ascii');
		$this->validateColumn('cachetype', 'cache', 'INTEGER', "0", true, '');
		$this->validateColumn('stamp', 'cache', 'INTEGER', "0", true, '');
		$this->validateColumn('metadata', 'cache', 'TEXT', NULL, false, 'ascii');
		$this->validateColumn('serialized', 'cache', 'BOOLEAN', NULL, false, '');
		$this->validateColumn('content', 'cache', 'MEDIUMBLOB', NULL, false, '');
		$this->alterStorageEngine("cache", "InnoDB");

		# ---- permaudit table ---- #
		$this->createTable('permaudit', "ascii");
		$this->validateColumn('stamp', 'permaudit', 'INTEGER', "0", true, '');
		$this->validateColumn('userid', 'permaudit', 'INTEGER', "0", true, '');
		$this->validateColumn('permissionid', 'permaudit', 'INTEGER', "0", true, '');
		$this->validateColumn('objectid', 'permaudit', "VARCHAR(128)", "''", true, 'ascii');
		$this->validateColumn('result', 'permaudit', "BOOLEAN", "true", true, '');
		$this->validateColumn('ipaddr', 'permaudit', "VARCHAR(45)", "''", true, 'ascii');
		$this->alterStorageEngine("permaudit", "InnoDB");

		##############################################################################################
		### deprecation van oude Spotweb versies #####################################################
		##############################################################################################
		if ($this->_spotdb->getSchemaVer() > 0.00 && ($this->_spotdb->getSchemaVer() < 0.34)) {
			if ($this->_spotdb->getSchemaVer() > 0.00 && ($this->_spotdb->getSchemaVer() < 0.30)) {
				throw new Exception("Je huidige Spotweb database installatie is te oud om in een keer te upgraden naar deze versie." . PHP_EOL .
									"Download een eerdere versie van spotweb (https://github.com/spotweb/spotweb/zipball/da6ba29071c49ae88823cccfefc39375b37e9bee), " . PHP_EOL . 
									"draai daarmee upgrade-db.php en als die succesvol is, start dan nogmaals de upgrade via deze versie.");
			} # if

			# Tabellen terug samenvoegen in een MyISAM tabel
			if (($this->_spotdb->getSchemaVer() < 0.34) && ($this->tableExists('spottexts'))) {
				throw new Exception("Je huidige Spotweb database installatie is te oud om in een keer te upgraden naar deze versie." . PHP_EOL .
									"Download een eerdere versie van spotweb (https://github.com/spotweb/spotweb/zipball/48bc94a63f94959f9fe6b2372b312e35a4d09997), " . PHP_EOL . 
									"draai daarmee upgrade-db.php en als die succesvol is, start dan nogmaals de upgrade via deze versie.");
			} # if
		} # if

		/*
		 * Convert the information from 'spotsfull' to 'spots' table
		 */
		if (($this->_spotdb->getSchemaVer() < 0.48) && ($this->_spotdb->getSchemaVer() > 0.00)) {
			echo PHP_EOL . PHP_EOL;
			echo 'Converting your spotsfull data to another format' . PHP_EOL;
			echo 'Please note - if you had spotsfull enabled, this can take a long time' . PHP_EOL;
			echo PHP_EOL . PHP_EOL;

			# Empty the blacklist table because the userid column is renamed to spotterid
			$tmp = $this->_dbcon->rawExec("TRUNCATE spotteridblacklist");

			# Update the spotterid field with the userid field
			$this->_dbcon->rawExec("UPDATE commentsfull SET spotterid = userid");

			# MySQL specifieke syntax to update the spots
			if ($this instanceof SpotStruct_mysql) {
				$this->_dbcon->rawExec("UPDATE spots s, spotsfull f SET s.spotterid = f.userid WHERE (s.messageid = f.messageid)");
			} # if

			# PostgreSQL (?) specifieke syntax
			if ($this instanceof SpotStruct_pgsql) {
				$this->_dbcon->rawExec("UPDATE spots s SET spotterid = spotsfull.userid FROM spotsfull WHERE (s.messageid = spotsfull.messageid)");
			} # if
		} # if

		# cache omzetten naar nieuw systeem
		if (($this->_spotdb->getSchemaVer() < 0.51) && ($this->tableExists('cachetmp'))) {
			$cachetmpCount = $this->_dbcon->singleQuery("SELECT COUNT(1) FROM cachetmp;");
			if ($cachetmpCount > 7500) {
				$dbname = $this->_dbcon->singleQuery("SELECT DATABASE();");
				echo PHP_EOL;
				echo "Converting the cache is not necessary to continue working with SpotWeb. If you don't want" . PHP_EOL;
				echo "to wait for this conversion, please enter the following command in MySQL or phpMyAdmin:" . PHP_EOL;
				echo "\tDROP TABLE " . $dbname . ".cachetmp;" . PHP_EOL . PHP_EOL;
				echo "If you like to convert the cache, enter:" . PHP_EOL;
				echo "\tINSERT INTO " . $dbname . ".cache SELECT resourceid, cachetype, stamp, metadata, serialized, COMPRESS(content) FROM " . $dbname . ".cachetmp;" . PHP_EOL;
				echo "\tDROP TABLE " . $dbname . ".cachetmp;" . PHP_EOL . PHP_EOL;
				echo "After this operation you must run upgrade-db.php again." . PHP_EOL . PHP_EOL;
				die();
			} # if
		} # if

		# En creeer de diverse indexen
		# ---- Indexen op spots -----
		$this->validateIndex("idx_spots_1", "UNIQUE", "spots", array("messageid"));
		$this->validateIndex("idx_spots_2", "", "spots", array("stamp"));
		$this->validateIndex("idx_spots_3", "", "spots", array("reversestamp"));
		$this->validateIndex("idx_spots_4", "", "spots", array("category", "subcata", "subcatb", "subcatc", "subcatd", "subcatz"));
		$this->validateIndex("idx_spots_5", "", "spots", array("spotterid"));
		$this->validateFts("idx_fts_spots", "spots", 
					array(1 => "poster",
					      2 => 'title',
						  3 => 'tag'));

		# ---- Indexen op nntp ----
		$this->validateIndex("idx_nntp_1", "UNIQUE", "nntp", array("server"));
		
		# ---- Indexen op spotsfull ----
		$this->validateIndex("idx_spotsfull_1", "UNIQUE", "spotsfull", array("messageid"));

		# ---- Indexen op commentsfull ----
		$this->validateIndex("idx_commentsfull_1", "UNIQUE", "commentsfull", array("messageid"));

		# ---- Indexen op commentsxover ----
		$this->validateIndex("idx_commentsxover_1", "UNIQUE", "commentsxover", array("messageid"));
		$this->validateIndex("idx_commentsxover_2", "", "commentsxover", array("nntpref"));

		# ---- Indexen op reportsxover ----
		$this->validateIndex("idx_reportsxover_1", "UNIQUE", "reportsxover", array("messageid"));
		$this->validateIndex("idx_reportsxover_2", "", "reportsxover", array("nntpref"));

		# ---- Indexen op reportsposted ----
		$this->validateIndex("idx_reportsposted_1", "UNIQUE", "reportsposted", array("messageid"));
		$this->validateIndex("idx_reportsposted_2", "UNIQUE", "reportsposted", array("inreplyto", "ouruserid"));
		$this->validateIndex("idx_reportspostedrel_1", "", "reportsposted", array("ouruserid"));
		
		# ---- Indexen op commentsposted ----
		$this->validateIndex("idx_commentsposted_1", "UNIQUE", "commentsposted", array("messageid"));
		$this->validateIndex("idx_commentspostedrel_1", "", "commentsposted", array("ouruserid"));

		# ---- Indexen op spotsposted ----
		$this->validateIndex("idx_spotsposted_1", "UNIQUE", "spotsposted", array("messageid"));
		$this->validateIndex("idx_spotspostedrel_1", "", "spotsposted", array("ouruserid"));

		# ---- Indexen op settings ----
		$this->validateIndex("idx_settings_1", "UNIQUE", "settings", array("name"));

		# ---- Indexen op usersettings ----
		$this->validateIndex("idx_usersettings_1", "UNIQUE", "usersettings", array("userid"));

		# ---- Indexen op users ----
		$this->validateIndex("idx_users_1", "UNIQUE", "users", array("username"));
		$this->validateIndex("idx_users_2", "UNIQUE", "users", array("mail"));
		$this->validateIndex("idx_users_3", "", "users", array("deleted"));
		$this->validateIndex("idx_users_4", "UNIQUE", "users", array("apikey"));

		# ---- Indexen op sessions
		$this->validateIndex("idx_sessions_1", "UNIQUE", "sessions", array("sessionid"));
		$this->validateIndex("idx_sessions_2", "", "sessions", array("lasthit"));
		$this->validateIndex("idx_sessions_3", "", "sessions", array("sessionid", "userid"));
		$this->validateIndex("idx_sessionsrel_1", "", "sessions", array("userid"));

		# ---- Indexen op spotstatelist ----
		$this->validateIndex("idx_spotstatelist_1", "UNIQUE", "spotstatelist", array("messageid", "ouruserid"));
		$this->validateIndex("idx_spotstatelistrel_1", "", "spotstatelist", array("ouruserid"));

		# ---- Indexen op securitygroups ----
		$this->validateIndex("idx_securitygroups_1", "UNIQUE", "securitygroups", array("name"));

		# ---- Indexen op grouppermissions ----
		$this->validateIndex("idx_grouppermissions_1", "UNIQUE", "grouppermissions", array("groupid", "permissionid", "objectid"));

		# ---- Indexen op usergroups ----
		$this->validateIndex("idx_usergroups_1", "UNIQUE", "usergroups", array("userid", "groupid"));
		$this->validateIndex("idx_usergroupsrel_1", "", "usergroups", array("groupid"));

		# ---- Indexen op notifications ----
		$this->validateIndex("idx_notifications_1", "", "notifications", array("userid"));
		$this->validateIndex("idx_notifications_2", "", "notifications", array("sent"));

		# ---- Indexen op filters ----
		$this->validateIndex("idx_filters_1", "", "filters", array("userid", "filtertype", 'tparent', 'torder'));

		# ---- Indexen op spotteridblacklist ----
		$this->validateIndex("idx_spotteridblacklist_1", "UNIQUE", "spotteridblacklist", array("spotterid", "ouruserid"));

		# ---- Indexen op cache ----
		$this->validateIndex("idx_cache_1", "UNIQUE", "cache", array("resourceid", "cachetype"));
		$this->validateIndex("idx_cache_2", "", "cache", array("cachetype", "stamp"));

		# leg foreign keys aan
		$this->addForeignKey('usersettings', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('sessions', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('spotstatelist', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('usergroups', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('usergroups', 'groupid', 'securitygroups', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('grouppermissions', 'groupid', 'securitygroups', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('commentsfull', 'messageid', 'commentsxover', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('notifications', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('commentsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('reportsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('filters', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		$this->addForeignKey('spotsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
		
		##############################################################################################
		# Hier droppen we kolommen ###################################################################
		##############################################################################################
		$this->dropColumn('filesize', 'spotsfull');
		$this->dropColumn('userid', 'spotsfull');
		$this->dropColumn('userid', 'spotteridblacklist');
		$this->dropColumn('userid', 'commentsfull');

		##############################################################################################
		# Hier droppen we tabellen ###################################################################
		##############################################################################################		
		$this->dropTable('webcache');
		$this->dropTable('cachetmp');

		# voeg het database schema versie nummer toe
		$this->_spotdb->updateSetting('schemaversion', SPOTDB_SCHEMA_VERSION);
	} # updateSchema

} # class
