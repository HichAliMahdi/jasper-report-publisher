<?php
    session_start(['read_and_close' => true]);
		require('../incl/const.php');
    require('../class/database.php');
		require('../class/datasource.php');
		require('../incl/jru-lib.php');
	
		# Add a child to root of $APP/WEB-INF/web.xml and $TOMCAT_HOME/conf/context.xml 
		function dom_xml_save($filename, $xmlstr){
			$doc = new DOMDocument();
			$doc->preserveWhiteSpace = false;
			$doc->formatOutput = true;
			$doc->loadXML($xmlstr);
			file_put_contents($filename, $doc->saveXML());
		}
		
		function ctx_xml_rm($filename, $name){
			$xml = simplexml_load_file($filename);
			list($elem) = $xml->xpath('/Context/Resource[@name="jdbc/'.$name.'"]');
			if($elem){
				unset($elem[0]);
				$xml->asXML($filename);
			}
		}
		
		function web_xml_rm($filename, $name){
			$xml = simplexml_load_file($filename, null, LIBXML_NONET | LIBXML_NSCLEAN);
			foreach ($xml->getDocNamespaces() as $prefix => $namespace) {
			    $xml->registerXPathNamespace($prefix ?: 'x', $namespace);
			}
			
			list($elem) = $xml->xpath('/x:web-app/x:resource-ref/x:res-ref-name[text() = "'.$name.'"]');
			if($elem){
			    $dom = dom_import_simplexml($elem);
                $dom->parentNode->remove();
				$xml->asXML($filename);
			}
		}
		
		function jri_add_pg_resource($ctxxml, $webxml, $name, $url, $user, $pass){
			$xml = simplexml_load_file($ctxxml);
			$res = $xml->addChild('Resource');
			$res->addAttribute('name',						'jdbc/'.$name);
			$res->addAttribute('auth', 						"Container");
			$res->addAttribute('type', 						"javax.sql.DataSource");
			$res->addAttribute('driverClassName', "org.postgresql.Driver");
			$res->addAttribute('maxTotal',				"20");
			$res->addAttribute('initialSize',			"0");
			$res->addAttribute('minIdle',					"0");
			$res->addAttribute('maxIdle',					"8");
			$res->addAttribute('maxWaitMillis',		"10000");
			$res->addAttribute('timeBetweenEvictionRunsMillis',	"30000");
			$res->addAttribute('minEvictableIdleTimeMillis',		"60000");
			$res->addAttribute('testWhileIdle',		"true");
			$res->addAttribute('validationQuery',	"select user");
			$res->addAttribute('maxAge',					"600000");
			$res->addAttribute('rollbackOnReturn',"true");
			$res->addAttribute('url',			$url);
			$res->addAttribute('username',$user);
			$res->addAttribute('password',$pass);
			dom_xml_save($ctxxml, $xml->asXML());
			
			$xml = simplexml_load_file($webxml);
			$res = $xml->addChild('resource-ref');
			$res->addChild('description',		'postgreSQL Datasource example');
			$res->addChild('res-ref-name',	$name);
			$res->addChild('res-type',			'javax.sql.DataSource');
			$res->addChild('res-auth',			'Container');
			dom_xml_save($webxml, $xml->asXML());
		}

		function jri_add_mysql_resource($ctxxml, $webxml, $name, $url, $user, $pass){
			$xml = simplexml_load_file($ctxxml);
			$res = $xml->addChild('Resource');
			$res->addAttribute('name',						'jdbc/'.$name);
			$res->addAttribute('auth', 						"Container");
			$res->addAttribute('type', 						"javax.sql.DataSource");
			$res->addAttribute('maxTotal',				"100");
			$res->addAttribute('maxIdle',					"20");
			$res->addAttribute('maxWaitMillis',		"10000");
			$res->addAttribute('driverClassName', "com.mysql.jdbc.Driver");
			$res->addAttribute('url',			$url);
			$res->addAttribute('username',$user);
			$res->addAttribute('password',$pass);
			dom_xml_save($ctxxml, $xml->asXML());
			
			
			$xml = simplexml_load_file($webxml);
			$res = $xml->addChild('resource-ref');
			$res->addChild('description',		'MySQL Datasource example');
			$res->addChild('res-ref-name',	$name);
			$res->addChild('res-type',			'javax.sql.DataSource');
			$res->addChild('res-auth',			'Container');
			dom_xml_save($webxml, $xml->asXML());
		}

		function jri_add_mssql_resource($ctxxml, $webxml, $name, $url, $user, $pass){
			$xml = simplexml_load_file($ctxxml);
			$res = $xml->addChild('Resource');
			$res->addAttribute('name',						'jdbc/'.$name);
			$res->addAttribute('auth', 						"Container");
			$res->addAttribute('type', 						"javax.sql.DataSource");
			$res->addAttribute('maxTotal',				"100");
			$res->addAttribute('maxIdle',					"30");
			$res->addAttribute('maxWaitMillis',		"10000");
			$res->addAttribute('driverClassName', "com.microsoft.sqlserver.jdbc.SQLServerDriver");
			$res->addAttribute('url',			$url);
			$res->addAttribute('username',$user);
			$res->addAttribute('password',$pass);
			dom_xml_save($ctxxml, $xml->asXML());
			
			$xml = simplexml_load_file($webxml);
			$res = $xml->addChild('resource-ref');
			$res->addChild('description',		'MSSQL Datasource example');
			$res->addChild('res-ref-name',	$name);
			$res->addChild('res-type',			'javax.sql.DataSource');
			$res->addChild('res-auth',			'Container');
			dom_xml_save($webxml, $xml->asXML());
		}
		
		function prop_rm_ds($filename, $ds_name){
			$ds_line = -1;
			
			$lines = file($filename, FILE_IGNORE_NEW_LINES);

			foreach($lines as $ln => $line) {
				if($line == '[datasource:'.$ds_name.']'){
					$ds_line = $ln;
					break;
				}
			}
			
			# comment out the datasource
			unset($lines[$ds_line]);
			$ds_line = $ds_line + 1;

			foreach(DS_KEYS as $k){
				$line = $lines[$ds_line];
				if(str_starts_with($line, $k.'=')){
					unset($lines[$ds_line]);
				}
				$ds_line = $ds_line + 1;
			}
			
			// save back to file
			$fp = fopen($filename, 'w');
			foreach($lines as $l){
				fwrite($fp, $l."\n");
			}
			fclose($fp);
			
			return $ds_line;
		}
		
		function prop_add_ds($filename, $ds){
			$fp = fopen($filename, 'a');
			fwrite($fp, "\n");
			fwrite($fp, '[datasource:'.$ds['name']."]\n");
			foreach(DS_KEYS as $k){
				fwrite($fp, $k.'='.$ds[$k]."\n");
			}
			fclose($fp);
		}
		
    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && in_array($_SESSION[SESS_USR_KEY]->accesslevel, ADMINISTRATION_ACCESS)) {
			
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
			$obj = new datasource_Class($database->getConn());

			# get property file
			$propfile = get_prop_file();
			$catalina_home = get_catalina_home();
						
			$webxml = $catalina_home.'/webapps/JasperReportsIntegration/WEB-INF/web.xml';
			$ctxxml = $catalina_home.'/conf/context.xml';

			$ds_id = 0;
			$ds_name = '';
			
			if(isset($_POST['id'])){
				$ds_id = intval($_POST['id']);
				
				$row = $obj->getById($ds_id);
				if($row === false){
					$result = ['success' => false, 'message' => 'Datasource doesn\'t exist!'];
					echo json_encode($result);
					exit;
				}
				$ds_name = $row['name'];
			}
			
      if(isset($_POST['save'])) {
        
        if($ds_id > 0) { // update
					
					if(!$obj->update($_POST)){
						$result = ['success' => false, 'message' => 'Datasource record update failed!'];
					}else{
						$ds_line = prop_rm_ds($propfile, $ds_name);

						if($ds_line == -1){
							$result = ['success' => false, 'message' => 'Datasource not found!'];
						}else{
							prop_add_ds($propfile, $_POST);
							
							if($_POST['type'] == 'jndi'){
								// update web.xml and context.xml							
								ctx_xml_rm($ctxxml, $ds_name);
								web_xml_rm($webxml, $ds_name);
								
											if(str_starts_with($_POST['url'], 'jdbc:postgresql')){		jri_add_pg_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
								}else if(str_starts_with($_POST['url'], 'jdbc:mysql')){				jri_add_mysql_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
								}else if(str_starts_with($_POST['url'], 'jdbc:sqlserver')){		jri_add_mssql_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
								}
							}
							
							$result = ['success' => true, 'message' => 'Datasource updated!'];
						}
					}

        } else { // insert

					$newId = $obj->create($_POST);
					if($newId == 0){
						$result = ['success' => true, 'message' => 'Datasource create failed'];
					}else{
						
						// remove any existing ds with same name
						prop_rm_ds($propfile, $_POST['name']);
						
						// append datasource to property file
						prop_add_ds($propfile, $_POST);

						if($_POST['type'] == 'jndi'){
							// update web.xml and context.xml
							
										if(str_starts_with($_POST['url'], 'jdbc:postgresql')){		jri_add_pg_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
							}else if(str_starts_with($_POST['url'], 'jdbc:mysql')){				jri_add_mysql_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
							}else if(str_starts_with($_POST['url'], 'jdbc:sqlserver')){		jri_add_mssql_resource(	$ctxxml, $webxml, $_POST['name'], $_POST['url'], $_POST['username'], $_POST['password']);
							}
						}	
					
						$result = ['success' => true, 'message' => 'Datasource Successfully Saved!', 'id' => $newId];
					}
        }
      
			} else if(isset($_POST['delete'])) {
				
				$row = $obj->getById($ds_id);
				
				$ref_ids = array();
				$ref_name = null;
				$acc_tbls = array('jasper', 'schedule');
				
				foreach($acc_tbls as $k){
					$rows = $database->getAll($k, 'datasource_id = '.$ds_id);							
					foreach($rows as $row){
						$ref_ids[] = $row['id'];
					}
					
					if(count($ref_ids) > 0){
						$ref_name = $k;
						break;
					}
				}						
				
				if(count($ref_ids) > 0){

					$result = ['success' => false, 'message' => 'Error: Can\'t delete because '.$ref_name.'(s) ' . implode(',', $ref_ids) . ' rely on datasource!' ];
				
				}else if(($row === false) || !$obj->delete($ds_id)){
					$result = ['success' => false, 'message' => 'Datasource delete failed!'];
				}else{
					$ds_line = prop_rm_ds($propfile, $ds_name);
					
					if($ds_line == -1){
						$result = ['success' => false, 'message' => 'Datasource '.$ds_name.'not found!'];
					}else{
						
						if($row['type'] == 'jndi'){
							// update web.xml and context.xml
							ctx_xml_rm($ctxxml, $ds_name);
							web_xml_rm($webxml, $ds_name);
						}
						
						$result = ['success' => true, 'message' => 'Data Successfully Deleted!'];
					}
				}
			} else if(isset($_POST['pwd_vis'])) {
				
				$row = $obj->getById($ds_id);
				if($row == FALSE){
					$result = ['success' => false, 'message' => 'Failed to get password!'];
				}else{
					$result = ['success' => true, 'message' => $row['password']];
				}
			} else if(isset($_POST['test'])) {
				
				$row = $obj->getById($ds_id);
				if($row == FALSE){
					$result = ['success' => false, 'message' => 'Datasource not found!'];
				}else{
					// Test the datasource connection
					$url = $row['url'];
					$username = $row['username'];
					$password = $row['password'];
					
					$db_type = '';
					$host = '';
					$port = '';
					$dbname = '';
					
					// Parse connection string based on database type
					if(str_starts_with($url, 'jdbc:postgresql')){
						$db_type = 'PostgreSQL';
						
						// Check if pgsql extension is loaded
						if(!extension_loaded('pgsql')){
							$result = ['success' => false, 'message' => "Failed to test $db_type connection.\\n\\nError: pgsql extension is not installed or enabled in PHP."];
						} else if(preg_match('/jdbc:postgresql:\\/\\/([^:\\/]+):?(\\d+)?\\\/([^?\\s]+)/', $url, $matches)){
							// jdbc:postgresql://host:port/database or jdbc:postgresql://host/database
							$host = $matches[1];
							$port = isset($matches[2]) && $matches[2] ? intval($matches[2]) : 5432;
							$dbname = $matches[3];
							
							try {
								$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password connect_timeout=5");
								if($conn){
									$version = pg_version($conn);
									pg_close($conn);
									$result = [
										'success' => true, 
										'message' => "Successfully connected to $db_type database.\\nHost: $host:$port\\nDatabase: $dbname\\nServer version: " . $version['server']
									];
								} else {
									$error = pg_last_error();
									$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nHost: $host:$port\\nDatabase: $dbname\\nError: " . ($error ? $error : 'Unknown error')];
								}
							} catch (Exception $e) {
								$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nError: " . $e->getMessage()];
							}
						} else {
							$result = ['success' => false, 'message' => 'Invalid PostgreSQL JDBC URL format.\\nExpected: jdbc:postgresql://host:port/database or jdbc:postgresql://host/database\\nReceived: ' . $url];
						}
						
					} else if(str_starts_with($url, 'jdbc:mysql')){
						$db_type = 'MySQL';
						
						// Check if mysqli extension is loaded
						if(!extension_loaded('mysqli')){
							$result = ['success' => false, 'message' => "Failed to test $db_type connection.\\n\\nError: mysqli extension is not installed or enabled in PHP."];
						} else if(preg_match('/jdbc:mysql:\/\/([^\/]+)\/([^?\s]+)/', $url, $matches)){
							// jdbc:mysql://host:port/database or jdbc:mysql://host/database
							$hostport = $matches[1];
							$dbname = $matches[2];
							
							// Parse host and port
							if(strpos($hostport, ':') !== false){
								list($host, $port) = explode(':', $hostport, 2);
								$port = intval($port);
							} else {
								$host = $hostport;
								$port = 3306;
							}
							
							try {
								$conn = @new mysqli($host, $username, $password, $dbname, $port);
								if($conn->connect_error){
									$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nHost: $host:$port\\nDatabase: $dbname\\nError: " . $conn->connect_error];
								} else {
									$version = $conn->server_info;
									$conn->close();
									$result = [
										'success' => true, 
										'message' => "Successfully connected to $db_type database.\\nHost: $host:$port\\nDatabase: $dbname\\nServer version: $version"
									];
								}
							} catch (Exception $e) {
								$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nError: " . $e->getMessage()];
							}
						} else {
							$result = ['success' => false, 'message' => 'Invalid MySQL JDBC URL format.\\nExpected: jdbc:mysql://host:port/database or jdbc:mysql://host/database\\nReceived: ' . $url];
						}
						
					} else if(str_starts_with($url, 'jdbc:sqlserver')){
						$db_type = 'MS SQL Server';
						
						// Check if sqlsrv extension is loaded
						if(!extension_loaded('sqlsrv')){
							$result = ['success' => false, 'message' => "Failed to test $db_type connection.\\n\\nError: sqlsrv extension is not installed or enabled in PHP."];
						} else if(preg_match('/jdbc:sqlserver:\\/\\/([^:;]+):?(\\d+)?;?.*databaseName=([^;]+)/', $url, $matches)){
							// jdbc:sqlserver://host:port;databaseName=database
							$host = $matches[1];
							$port = isset($matches[2]) && $matches[2] ? intval($matches[2]) : 1433;
							$dbname = $matches[3];
							
							try {
								$connectionInfo = array(
									"Database" => $dbname,
									"UID" => $username,
									"PWD" => $password,
									"LoginTimeout" => 5
								);
								
								$conn = @sqlsrv_connect($host . ',' . $port, $connectionInfo);
								if($conn === false){
									$errors = sqlsrv_errors();
									$error_msg = '';
									if($errors){
										foreach($errors as $error){
											$error_msg .= $error['message'] . '\\n';
										}
									}
									$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nHost: $host:$port\\nDatabase: $dbname\\nError: " . ($error_msg ? $error_msg : 'Unknown error')];
								} else {
									$server_info = sqlsrv_server_info($conn);
									sqlsrv_close($conn);
									$result = [
										'success' => true, 
										'message' => "Successfully connected to $db_type database.\\nHost: $host:$port\\nDatabase: $dbname\\nServer version: " . $server_info['SQLServerVersion']
									];
								}
							} catch (Exception $e) {
								$result = ['success' => false, 'message' => "Failed to connect to $db_type database.\\n\\nError: " . $e->getMessage()];
							}
						} else {
							$result = ['success' => false, 'message' => 'Invalid MS SQL Server JDBC URL format.\\nExpected: jdbc:sqlserver://host:port;databaseName=database\\nReceived: ' . $url];
						}
						
					} else {
						$result = ['success' => false, 'message' => 'Unsupported database type. Supported types are: PostgreSQL, MySQL, MS SQL Server'];
					}
				}
			}
    }

    echo json_encode($result);
?>
