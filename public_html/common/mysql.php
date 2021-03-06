<?php

class Sql
{
	function Sql ($host, $user, $password, $base)
	{
    $this -> debug = 0;
		$this->handle = @mysql_connect($host, $user, $password);
		if ($this->handle)
		{
			if ($base != '')
			{
				if(!@mysql_select_db($base))
				{
					@mysql_close($this->handle);
					return FALSE;
				}
			}
			return $this->handle;
		}
		return FALSE;
	}

	function Close ()
	{
		if ($this->handle)
		{
			return @mysql_close($this->handle);
		}
		return FALSE;
	}

	function Query ($sql)
	{
    $start = microtime(true);
		$result = mysql_unbuffered_query ($sql, $this->handle);
    $elapsed_secs = microtime(true) - $start;
    
    if ($elapsed_secs > 1)
    {
      trigger_error ("SLOW QUERY [$elapsed_secs sec]: $sql", E_USER_WARNING);
    }

		if (!$result)
		{
					echo mysql_error();
			return FALSE;

			}

		$array = FALSE;

		while ($row = mysql_fetch_assoc($result))
		{
			$array [] = $row;
		}
				echo mysql_error();
		return $array;
	}

	function GetAll ($table, $what = '*', $condition = '1', $limit = 0, $orderby = '')
	{
		$sql = "SELECT $what FROM $table WHERE $condition";

		if ($orderby)
			$sql .= " ORDER BY $orderby";

					if ($limit)
			$sql .= " LIMIT $limit";

		return $this->Query ($sql);
	}

	function Quote($value)
	{
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }

    if (!is_numeric($value)) {
        $value = "'" . mysql_real_escape_string($value) . "'";
    }

    return $value;
	}

	function QuoteAndClean($value)
	{
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($value);
    }

    $value = strip_tags ($value);

    if (!is_numeric($value)) {
        $value = "'" . mysql_real_escape_string($value) . "'";
    }

    return $value;
	}

	function Run ($request)
	{
    if ($this -> debug)
    {
      echo "$request<br>";
      return 1;
    }
		$res = mysql_unbuffered_query ($request, $this->handle);
		
		$error = mysql_error();
		
		if ($error)
		{
      echo "$request $error";
		}

		return $res;
	}

	function LastInsert ()
	{
		return mysql_insert_id($this->handle);
	}
	
	function GetAffectedCount()
	{
		return mysql_affected_rows($this -> handle);
	}
	
	function GetRow ($query)
	{
		$result = $this -> Query ($query);

		if (!$result)
			return FALSE;

		return $result[0];
	}
	
	function begin()
	{
    $this -> Run ('START TRANSACTION');
	}
	
	function commit()
	{
    $this -> Run ('COMMIT');
	}
	
	function rollback()
	{
    $this -> Run ('ROLLBACK');
	}

	function GetObject ($table, $id = FALSE)
	{
		if ($id == FALSE)
			$id = $this->LastInsert();
		$id = $this -> Quote ($id);
		$sql = "SELECT * FROM $table WHERE `id` = $id LIMIT 1";
		$result = mysql_unbuffered_query ($sql, $this->handle);

		if (!$result)
			return FALSE;


		return mysql_fetch_assoc($result);
	}

	function DeleteObject ($table, $id)
	{

		$id = $this -> Quote ($id);

		$req = "DELETE FROM $table WHERE `id` = $id LIMIT 1";

		return mysql_unbuffered_query ($req, $this->handle);
	}

}

?>