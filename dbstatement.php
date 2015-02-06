<?php
/**
 *
 * DbStatement, représentant un Statement PDO, étendu
 * avec des fonctionnalités de binding de valeurs / de debug
 * plus avancées et autres.
 * 
 * @author Alexis M.
 * @since 02/12/2014
 */
class DbStatement extends PDOStatement
{
    protected $keywords = array();
    
    /**
     * Valeurs matchées.
     * @var array
     */
    protected $boundValues = array();

    /**
     * Preview de la query de la statement.
     * @var string
     */
    protected $preview;

    /**
     * Durée de l'exécution (microsecondes).
     * @var integer
     */
    protected $duration;

    /**
     * Statement déjà exécutée ?
     * @var boolean
     */
    protected $executed = false;

    /**
     * Nombre d'exécution de la statement.
     * @var integer
     */
    protected $execCount = 0;

    /**
     * Query courante.
     * @var string
     */
    public $queryString;

    /**
     * "Bind" d'une valeur.
     * @param string $parameter Le paramètre pour lequel "bind" la value.
     * @param mixed $value La valeur à "bind".
     * @param string $PDOType Le type de valeur. Optionnel.
     * @return self
     */
    public function bindValue($parameter, $value, $PDOType = null)
    {
        if ($this->executed)
        {
            $this->boundValues = array();
            $this->executed = false;
        }

        $this->boundValues[] = array('parameter' => $parameter, 'value' => $value, 'PDOType' => $PDOType);
        parent::bindValue($parameter, $value, $PDOType);
        
        return $this;
    }

    /**
     * "Bind" de plusieurs valeurs.
     * @param array $sqlValues Les valeurs, sous la forme parameter => value.
     * 
     * @return self
     */
    public function bindValues($sqlValues = array())
    {
        if (empty($sqlValues))
        {
            return $this;
        }

        if (!is_array($sqlValues))
        {
            $sqlValues = array($sqlValues);
        }

        foreach ($sqlValues as $key => $value)
        {
            if (is_numeric($key))
            {
                $this->bindValue((int) $key + 1, $value, self::getPDOType($value));
            }
            else
            {
                // Les paramètres DOIVENT commencer par le caractère ':'.
                if (strpos($key, ':') !== 0)
                {
                    $key = ':' . $key;
                }
                $this->bindValue($key, $value, self::getPDOType($value));
            }
        }

        return $this;
    }

    /**
     * Execution de la query.
     * Mesure du temps d'éxécution.
     * @param array $input_parameters D'éventuels paramètres à passer
     * à la méthode execute().
     * @return self
     */
    public function execute($input_parameters = null)
    {
        $startTime = microtime(true);
        parent::execute($input_parameters);
        $endTime = microtime(true);

        $this->duration = round($endTime - $startTime, 4);
        $this->executed = true;
        $this->execCount++;

        return $this;
    }

    /**
     * Execute la statement à l'aide des paramètres passés
     * à la méthode.
     *
     * @param array $sqlValues : Des paramètres optionnels.
     * @return DbStatement instance
     */
    public function sql($sqlValues = array())
    {
        return $this->bindValues($sqlValues)->execute();
    }

    /**
     * Méthode de debugging d'une statement.
     *
     * @return DbStatement instance
     */
    public function debug()
    {
        $this->keywords = array();
        $this->preview = preg_replace("#\t+#", "\t", $this->queryString);

        // ? placeholders.
        if (array_key_exists(0, $this->boundValues) && $this->boundValues[0]['parameter'] === 1)
        {
            foreach ($this->boundValues as $boundParam)
            {
                $this->preview = preg_replace("/([\?])/", self::debugValue($boundParam), $this->preview, 1);
            }
        }
        else
        {
            foreach ($this->boundValues as $boundValue)
            {
                $this->keywords[] = $boundValue['parameter'];
            }
        }
        foreach ($this->keywords as $word)
        {
            foreach ($this->boundValues as $boundParam)
            {
                if ($boundParam['parameter'] == $word)
                {
                    $this->preview = preg_replace("/(\:\b".substr($word, 1)."\b)/i", self::debugValue($boundParam), $this->preview);
                }
            }
        }

        return $this;
    }

    /**
     * @{"inheritDoc"}
     */
    public function __toString()
    {
        return (string) $this->queryString;
    }

    /**
     * Debug d'une valeur d'un paramètre.
     * @param array Les informations du paramètre.
     * @return string
     */
    private static function debugValue($boundParam)
    {
        if (in_array($boundParam['PDOType'], array(PDO::PARAM_BOOL, PDO::PARAM_INT)))
        {
            return (int) $boundParam['value'];
        }
        elseif ($boundParam['PDOType'] == PDO::PARAM_NULL)
        {
            return 'NULL';
        }
        else
        {
            return (string) "'". addslashes($boundParam['value']) . "'";
        }
    }

    /**
     * Remplacement d'un array de valeurs par des placeholders ("?")
     * Example : array(0, 22, 99) ==> '?,?,?'
     * Usage : "WHERE VALUES IN (". DbStatement::PlaceHolders($myArray) .")"
     *
     * @param array $array
     * @return string Le placeholder.
     */
    public static function replaceByPlaceholders($array = array())
    {
        return implode(',', array_fill(0, count($array), '?'));
    }

    /**
     * Binding automatique des variables passées en paramètre.
     *
     * @param mixed value
     * @return Une constance de PDO.
     */
    public static function getPDOType($value)
    {
        switch (strtolower(gettype($value)))
        {
            case 'string':
                return (strtoupper($value) == 'NULL') ? PDO::PARAM_NULL : PDO::PARAM_STR;
            case 'int':
            case 'integer':
                return PDO::PARAM_INT;
            case 'double':
            case 'float':
                return PDO::PARAM_STR;
            case 'bool':
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'null':
                return PDO::PARAM_NULL;
            default:
                return PDO::PARAM_STR;
        }

    }
}