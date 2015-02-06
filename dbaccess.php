<?php
/**
 *
 * Classe d'abstraction de PDO.
 * Inspiré de :
 * @link https://github.com/bpolaszek/PDOExtended/blob/master/PDOExtended/PDOExtended.php
 * 
 * @author Alexis M.
 * @since 01/12/2014
 */
class DbAccess
{
	/**
	 * @var \PDO
	 */
	protected $PDO;

	/**
	 * La dernière Statement gérée.
	 * @var \PDOStatement
	 */
	protected $latestStatement;

	/**
	 * Statements stockés (requêtes préparées).
	 * @var array
	 */
	protected static $statements = array();

	/**
	 * Récupération d'une connexion à la BDD.
	 * @param string $key L'éventuelle clé de connexion.
	 * @param boolean $refresh Indique si le fichier de configuration
	 * doit être relu (et re-dump) pour récupérer les infos de connexion.
	 */
	public function __construct($key = null)
	{
		// Initialisation de la connexion.
		$this->initConnection($key);
	}

	/**
     * Constructeur permettant le chaînage des requêtes.
     * Exemple : $q = DbAccess::create('beemoov_dev')->query(...);
     *
     * @return self
     */
    public static function create($key = null)
    {
        $currentClass = new \ReflectionClass(get_called_class());
        
        return $currentClass->NewInstanceArgs(func_get_args());
    }

    /**
     * Destructeur : déconnexion.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Fermeture de la connexion.
     *
     * @return self
     */
    public function disconnect()
    {
        $this->PDO = null;
        
        return $this;
    }

	/**
	 * Initialisation de la connexion donnée.
	 * @return self
	 */
	protected function initConnection($key = null)
	{
		// Si la clé est omise => Clé par défaut.
		if (!$key)
		{
			$key = $this->getConfigDefaultConnectionKey();
		}

		$connectionInformation = DbConfig::getInstance()->getConfig($key);

		/*
		 * Construction de la chaîne de connexion.
		 */
		$dsn = $this->createDSNFromConnectionData($connectionInformation);
		$user = $this->getUserFromConnectionData($connectionInformation);
		$password = $this->getPasswordFromConnectionData($connectionInformation);

		return $this->connect($dsn, $user, $password);
	}

	/**
	 * Connexion via PDO à l'aide des credentials fournis.
	 * @param string $dsn Le DSN sur lequel se connecter.
	 * @param string $user Le nom d'utilisateur.
	 * @param string $password L'éventuel mot de passe.
	 * @return self
	 */
	public function connect($dsn, $user, $password = null)
	{
		$statementClass = $this->getStatementClass();

		try
		{
			// UTF-8 only.
			$options = array(
		    	PDO::MYSQL_ATTR_INIT_COMMAND    => 'SET NAMES utf8'
		  	);

			// Exceptions ON
			$this->PDO = new \PDO($dsn, $user, $password, $options);
			$this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// Spécification des statements custom.
			if (is_string($statementClass) && class_exists($statementClass))
			{
				$this->PDO->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('DbStatement'));
			}

			$this->PDO->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

			return $this;
		} catch (\PDOException $e) {
			trigger_error('Couldn\'t connect do database. Please check your connection information.', E_USER_ERROR);
		}
	}


	/**
     * Préparation d'une SQL Statement.
     *
     * @param string $sqlString : La query SQL.
     * @param array $sqlValues : Optional PDO Values to bind
     * @param array $driverOptions
     * @return PDOStatementExtended statement
     */
	public function prepare($sqlString, $sqlValues = array(), $driverOptions = array())
	{
		if (!($this->PDO instanceof \PDO))
		{
			throw new \PDOException('PDO Connection doesn\'t exist.');
		}

		// Préparation de la statement et ajout à la pile.
		$statement = $this->addStatement($sqlString, $driverOptions);

		if ($statement instanceof \PDOStatement)
		{
			$this->setLatestStatement($statement);
		}

		if (!empty($sqlValues))
		{
			$statement->bindValues($sqlValues);
		}

		return $statement;
    }

	/**
	 * Prépare une \PDOStatement et l'éxécute.
	 *
	 * @param mixed $sqlString : SQL Query (String or instanceof PDOStatement)
	 * @param array $sqlValues : Optional PDO Values to bind
	 * @param array $driverOptions
	 * @return PDOStatementExtended statement (executed)
	 * @access public
	 */
	public function sql($sqlString, $sqlValues = array(), $driverOptions = array())
	{
		if (!($this->PDO instanceof \PDO))
		{
			throw new \PDOException("PDO Connection isn't active.");
		}

		$statement = ($sqlString instanceof \PDOStatement) ? $sqlString : $this->prepare($sqlString, $sqlValues, $driverOptions);
		if ($statement instanceof \PDOStatement)
		{
			$this->setLatestStatement($statement);
		}

		// bind des valeurs.
		$statement->bindValues($sqlValues);

		$statement->execute();

		return $statement;
	}

    /**
     * Ajout arbitraire d'un statement à la pile des statements.
     * @param mixed $statement Une statement, pouvant être une chaîne
     * de caractères ou un objet PDOStatement.
     * @return \PDOStatement Le statement ajouté à la pile.
     */
    public function addStatement($statement, $driverOptions)
    {
    	if ($statement instanceof \PDOStatement)
    	{
    		$statementString = $statement->queryString;
    	}
    	elseif (is_string($statement))
    	{
    		$statementString = $statement;
    		$statement = $this->PDO->prepare($statementString, $driverOptions);
    	}
    	$sqlFootprint = md5($statementString);
    	if (!array_key_exists($sqlFootprint, self::$statements))
    	{
    		self::$statements[$sqlFootprint] = $statement;
    	}
    	
    	return $statement;
    }

    /**
     * Récupération de l'ensemble des statements définies
     * à partir de l'instance.
     * @return array
     */
    public static function getStatements()
    {
    	return self::$statements;
    }


    /**
     * Ajout d'une \PDOStatement en tant que dernière statement
     * traitée.
     * @param \PDOStatement $statement La statement à ajouter.
     * @return self
     */
    private function setLatestStatement(\PDOStatement $statement)
    {
    	$this->latestStatement = $statement;

    	return $this;
    }

	/**
	 * Création du DSN à partir des informations de connexion.
	 * @param array $data Un array de données fournies par la conf. de la
	 * Db.
	 * @return string Le DSN, utilisable par PDO.
	 */
	private function createDSNFromConnectionData($data)
	{
		$driver = $data['driver'] ?: $this->getConfigDriver();
		$dbName = $data['dbname'];
		$hostname = $data['host'];

		$dsn = $driver . ':';
		$dsn .= 'dbname=' . $dbName;
		$dsn .= ';host=' . $hostname;

		return $dsn;
	}

	/**
	 * Récupération du nom la classe représentant les statements.
	 * @return string
	 */
	private function getStatementClass()
	{
		return DbConfig::getInstance()->getStatementClass();
	}

	/**
	 * Récupération du driver par défaut à partir de la configuration
	 * des infos de la BDD.
	 * @return string
	 */
	private function getConfigDriver()
	{
		return DbConfig::getInstance()->getDriver();
	}

	/**
	 * Récupération de la clé de connexion par défaut.
	 * @return string
	 */
	private function getConfigDefaultConnectionKey()
	{
		return DbConfig::getInstance()->getDefaultKey();
	}

	/**
	 * Récupération du user à partir des informations
	 * de connexion.
	 * @param array $data Les infos de connexion.
	 * @return string
	 */
	private function getUserFromConnectionData($data)
	{
		return $data['user'];
	}

	/**
	 * Récupération du password à partir des informations
	 * de connexion.
	 * @param array $data Les infos de connexion.
	 * @return string
	 */
	private function getPasswordFromConnectionData($data)
	{
		return $data['password'];
	}

	/**
	 * Récupération de PDO.
	 * @return \PDO
	 */
	public function getPDO()
	{
		return $this->PDO;
	}

	/**
	 * Méthode magique d'accès aux méthodes de PDO
	 * si aucune ne bind DbAccess. A noter que nous ne
	 * contrôlons pas l'existence de la méthode par choix :
	 * l'utilisateur aura ainsi bien une erreur de non existence
	 * de méthode auprès de PDO.
	 * @param string $name La méthode à appeler.
	 * @param array $args Les paramètres de la méthode à appeler.
	 */
	public function __call($name, array $args)
	{
		if (!($this->PDO instanceof \PDO))
		{
			throw new \PDOException("No connection found.");
		}
		return call_user_func_array(array($this->PDO, $name), $args);
    }
}