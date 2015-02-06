<?php
use Symfony\Component\Yaml\Yaml;

/**
 *
 * Classe d'abstraction servant de lien entre la configuration
 * de la Db via les fichiers de configuration et la couche
 * d'accès à la Db.	
 * 
 * @todo Supprimer la dépendance forte au composant YAML de Symfony.
 * Privilégier une approche générique => Passage d'un fournisseur de configuration
 * générique (interface ?) permettant de récupérer d'une façon homogène la config.
 * 
 * @author Alexis M.
 * @since 01/12/2O14
 */
class DbConfig
{
	// Fichier de configuration et des infos de connexion.
	const DATABASE_CONFIG_FILE = 'dal/dal.yml';
	// Clé ciblant les informations de connexion.
	const CONNECTIONS_CONFIG_KEY = 'connections';
	// Driver par défaut.
	const DEFAULT_DRIVER = 'mysql';

	/**
	 * Dump de la configuration YML des informations
	 * de la BDD.
	 * @var array
	 */
	protected static $config;

	/**
	 * Instance (Singleton) de DbConfig.
	 * @var DbConfig
	 */
	protected static $instance;

	private function __construct()
	{
		if (!is_array(self::$config))
		{
			$this->refresh();
		}
	}

	public static function getInstance()
	{
		return self::$instance ?: new DbConfig();
	}

	/**
	 * Récupération de la configuration complète.
	 * @param string $key Une éventuelle clé, spécifiant que
	 * l'on souhaite récupérer les informations de connexion
	 * liées à une configuration en particulier (e.g. "beemoov_dev").
	 * @return array
	 */
	public function getConfig($key = null)
	{
		// Récupère-t-on les infos pour une config. particulière ?
		if ($key)
		{
			return $this->getConnectionInformationFromKey($key);
		}

		return self::$config;
	}

	/**
	 * Récupération des informations d'une connexion à partir
	 * d'une clé.
	 * @throws MalformedDbConfigurationException Si la validation
	 * des informations de connexion a échoué.
	 * @param string $key La clé des informations de connexion.
	 * @return array
	 */
	protected function getConnectionInformationFromKey($key)
	{
		$config = $this->getConfig();

		$connectionInformation = $config[self::CONNECTIONS_CONFIG_KEY][$key];

		/*
		 * Validation des informations de connexion.
		 */
		if (!$this->validateConnectionInformation($connectionInformation))
		{
			throw new MalformedDbConfigurationException(
				sprintf('You seem to have an invalid configuration file. Check your %s file.',
				self::DATABASE_CONFIG_FILE
				)
			);
		}

		return $config[self::CONNECTIONS_CONFIG_KEY][$key];
	}

	/**
	 * Mise à jour de la config, via la récupération du fichier
	 * de configuration.
	 * @return array
	 */
	protected function refresh()
	{
		$configPath = CONFIG_ROOT . '/' . self::DATABASE_CONFIG_FILE;

		if (file_exists($configPath) && $file = file_get_contents($configPath))
		{
			try
			{
				self::$config = Yaml::parse($file);
				$a = self::$config;
			} catch(Exception $e) {
				return array();
			}
		}
		else
		{
			return array();
		}
		return $a;
	}

	/**
	 * Récupération de la classe Statement par défaut.
	 * @return string
	 */
	public function getStatementClass()
	{
		return $this->getConfig()['default']['statement_class'];
	}

	/**
	 * Récupération du driver défini.
	 * @return string
	 */
	public function getDriver()
	{
		return $this->getConfig()['default']['driver'] ?: self::DEFAULT_DRIVER;
	}

	/**
	 * Récupération de la clé de connexion par défaut
	 * potentiellement définie dans la configuration de la
	 * BDD.
	 * @return string
	 */
	public function getDefaultKey()
	{
		return $this->getConfig()['default']['connexion'];
	}

	/**
	 * Validation des informations de connexion.
	 * @param array $connectionInformation
	 * @return boolean
	 */
	protected function validateConnectionInformation($connectionInformation)
	{
		if (isset($connectionInformation['host']) &&
			isset($connectionInformation['dbname']) &&
			isset($connectionInformation['user']) &&
			isset($connectionInformation['password']))
		{
			return true;
		}
		return false;
	}
}