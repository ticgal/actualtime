<?php

class PluginActualtimeApirest extends Glpi\Api\API
{
	protected $request_uri;
	protected $url_elements;
	protected $verb;
	protected $parameters;
	protected $debug = 0;
	protected $format = "json";

	public static function getTypeName($nb = 0)
	{
		return __('ActualTime rest API', 'actualtime');
	}

	public function manageUploadedFiles()
	{
		foreach (array_keys($_FILES) as $filename) {
			// Randomize files names
			$rand_name = uniqid('', true);
			if (is_array($_FILES[$filename]['name'])) {
				foreach ($_FILES[$filename]['name'] as &$name) {
					$name = $rand_name . $name;
				}
			} else {
				$name = &$_FILES[$filename]['name'];
				$name = $rand_name . $name;
			}

			$upload_result
				= GLPIUploadHandler::uploadFiles([
					'name'           => $filename,
					'print_response' => false
				]);
			foreach ($upload_result as $uresult) {
				$this->parameters['input']->_filename[] = $uresult[0]->name;
				$this->parameters['input']->_prefix_filename[] = $uresult[0]->prefix;
			}
			$this->parameters['upload_result'][] = $upload_result;
		}
	}

	public function parseIncomingParams($is_inline_doc = false)
	{

		$parameters = [];

		// first of all, pull the GET vars
		if (isset($_SERVER['QUERY_STRING'])) {
			parse_str($_SERVER['QUERY_STRING'], $parameters);
		}

		// now how about PUT/POST bodies? These override what we got from GET
		$body = trim($this->getHttpBody());
		if (strlen($body) > 0 && $this->verb == "GET") {
			// GET method requires an empty body
			$this->returnError("GET Request should not have json payload (http body)", 400, "ERROR_JSON_PAYLOAD_FORBIDDEN");
		}

		$content_type = "";
		if (isset($_SERVER['CONTENT_TYPE'])) {
			$content_type = $_SERVER['CONTENT_TYPE'];
		} else if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
			$content_type = $_SERVER['HTTP_CONTENT_TYPE'];
		} else {
			if (!$is_inline_doc) {
				$content_type = "application/json";
			}
		}

		if (strpos($content_type, "application/json") !== false) {
			if ($body_params = json_decode($body)) {
				foreach ($body_params as $param_name => $param_value) {
					$parameters[$param_name] = $param_value;
				}
			} else if (strlen($body) > 0) {
				$this->returnError("JSON payload seems not valid", 400, "ERROR_JSON_PAYLOAD_INVALID", false);
			}
			$this->format = "json";
		} else if (strpos($content_type, "multipart/form-data") !== false) {
			if (count($_FILES) <= 0) {
				// likely uploaded files is too big so $_REQUEST will be empty also.
				// see http://us.php.net/manual/en/ini.core.php#ini.post-max-size
				$this->returnError("The file seems too big", 400, "ERROR_UPLOAD_FILE_TOO_BIG_POST_MAX_SIZE", false);
			}

			// with this content_type, php://input is empty... (see http://php.net/manual/en/wrappers.php.php)
			if (!$uploadManifest = json_decode($_REQUEST['uploadManifest'])) {
				$this->returnError("JSON payload seems not valid", 400, "ERROR_JSON_PAYLOAD_INVALID", false);
			}
			foreach ($uploadManifest as $field => $value) {
				$parameters[$field] = $value;
			}
			$this->format = "json";

			// move files into _tmp folder
			$parameters['upload_result'] = [];
			$parameters['input']->_filename = [];
			$parameters['input']->_prefix_filename = [];
		} else if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
			parse_str($body, $postvars);
            /** @var array $postvars */
            foreach ($postvars as $field => $value) {
                // $parameters['input'] needs to be an object when process API Request
                if ($field === 'input') {
                    $value = (object) $value;
                }
                $parameters[$field] = $value;
            }
			$this->format = "html";
		} else {
			$this->format = "html";
		}

		// retrieve HTTP headers
		$headers = getallheaders();
        if (false !== $headers && count($headers) > 0) {
            $fixedHeaders = [];
            foreach ($headers as $key => $value) {
                $fixedHeaders[ucwords(strtolower($key), '-')] = $value;
            }
            $headers = $fixedHeaders;
        }

		// try to retrieve basic auth
		if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			$parameters['login']    = $_SERVER['PHP_AUTH_USER'];
			$parameters['password'] = $_SERVER['PHP_AUTH_PW'];
		}

		// try to retrieve user_token in header
		if (isset($headers['Authorization']) && (strpos($headers['Authorization'], 'user_token') !== false)) {
			$auth = explode(' ', $headers['Authorization']);
			if (isset($auth[1])) {
				$parameters['user_token'] = $auth[1];
			}
		}

		// try to retrieve session_token in header
		if (isset($headers['Session-Token'])) {
			$parameters['session_token'] = $headers['Session-Token'];
		}

		// try to retrieve app_token in header
		if (isset($headers['App-Token'])) {
			$parameters['app_token'] = $headers['App-Token'];
		}

		// check boolean parameters
		foreach ($parameters as $key => &$parameter) {
			if ($parameter === "true") {
				$parameter = true;
			}
			if ($parameter === "false") {
				$parameter = false;
			}
		}

		$this->parameters = $parameters;

		return "";
	}

	private function inputObjectToArray($input)
	{
		if (is_object($input)) {
			$input = get_object_vars($input);
		}

		if (is_array($input)) {
			foreach ($input as &$sub_input) {
				$sub_input = self::inputObjectToArray($sub_input);
			}
		}

		return $input;
	}


	protected function initEndpoint($unlock_session = true, $endpoint = "")
	{

		if ($endpoint === "") {
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			$endpoint = $backtrace[1]['function'];
		}
		$this->checkAppToken();
		$this->logEndpointUsage($endpoint);
		$this->checkSessionToken();
		if ($unlock_session) {
			$this->unlockSessionIfPossible();
		}
	}

	/**
	 * Check if the app_toke in case of config ask to
	 *
	 * @return void
	 */
	private function checkAppToken()
	{

		// check app token (if needed)
		if (!isset($this->parameters['app_token'])) {
			$this->parameters['app_token'] = "";
		}
		if (!$this->apiclients_id = array_search($this->parameters['app_token'], $this->app_tokens)) {
			if ($this->parameters['app_token'] != "") {
				$this->returnError(__("parameter app_token seems wrong"), 400, "ERROR_WRONG_APP_TOKEN_PARAMETER");
			} else {
				$this->returnError(__("missing parameter app_token"), 400, "ERROR_APP_TOKEN_PARAMETERS_MISSING");
			}
		}
	}

	/**
	 * Log usage of the api into glpi historical or log files (defined by api config)
	 *
	 * It stores the ip and the username of the current session.
	 *
	 * @param string $endpoint function called by api to log (default '')
	 *
	 * @return void
	 */
	private function logEndpointUsage($endpoint = "")
	{

		$username = "";
		if (isset($_SESSION['glpiname'])) {
			$username = "(" . $_SESSION['glpiname'] . ")";
		}

		$apiclient = new APIClient;
		if ($apiclient->getFromDB($this->apiclients_id)) {
			$changes = [
				0,
				"",
				"Enpoint '$endpoint' called by " . $this->iptxt . " $username"
			];

			switch ($apiclient->fields['dolog_method']) {
				case APIClient::DOLOG_HISTORICAL:
					Log::history($this->apiclients_id, 'APIClient', $changes, 0, Log::HISTORY_LOG_SIMPLE_MESSAGE);
					break;

				case APIClient::DOLOG_LOGS:
					Toolbox::logInFile("api", $changes[2] . "\n");
					break;
			}
		}
	}

	/**
	 * Unlock the current session (readonly) to permit concurrent call
	 *
	 * @return void
	 */
	private function unlockSessionIfPossible()
	{

		if (!$this->session_write) {
			session_write_close();
		}
	}

	/**
	 * Get last message added in $_SESSION by Session::addMessageAfterRedirect
	 *
	 * @return array  of messages
	 */
	private function getGlpiLastMessage()
	{
		global $DEBUG_SQL;

		$all_messages             = [];

		$messages_after_redirect  = [];

		if (isset($_SESSION["MESSAGE_AFTER_REDIRECT"]) && count($_SESSION["MESSAGE_AFTER_REDIRECT"]) > 0) {
			$messages_after_redirect = $_SESSION["MESSAGE_AFTER_REDIRECT"];
			// Clean messages
			$_SESSION["MESSAGE_AFTER_REDIRECT"] = [];
		};

		// clean html
		foreach ($messages_after_redirect as $messages) {
			foreach ($messages as $message) {
				$all_messages[] = Toolbox::stripTags($message);
			}
		}

		// get sql errors
		if (count($all_messages) <= 0 && ($DEBUG_SQL['errors'] ?? null) !== null) {
			$all_messages = $DEBUG_SQL['errors'];
		}

		if (!end($all_messages)) {
			return '';
		}
		return end($all_messages);
	}

	/**
	 * Retrieve in url_element the current id. If we have a multiple id (ex /Ticket/1/TicketFollwup/2),
	 * it always find the second
	 *
	 * @return integer|boolean id of current itemtype (or false if not found)
	 */
	private function getId()
	{

		$id = isset($this->url_elements[1]) && is_numeric($this->url_elements[1]) ? intval($this->url_elements[1]) : false;
		$additional_id = isset($this->url_elements[3]) && is_numeric($this->url_elements[3]) ? intval($this->url_elements[3]) : false;

		if ($additional_id || isset($this->parameters['parent_itemtype'])) {
			$this->parameters['parent_id'] = $id;
			$id = $additional_id;
		}

		return $id;
	}

	private function pluginActivated($name = 'actualtime')
	{

		$plugin = new Plugin();

		if (!$plugin->isActivated($name)) {
			$this->returnError("Plugin disabled", 400, "ERROR_PLUGIN_DISABLED");
		}
	}

	/**
	 * List of API ressources for which a valid session isn't required
	 *
	 * @return array
	 */
	protected function getRessourcesAllowedWithoutSession(): array
	{
		return [
			"initOauth",
			"firebaseOauth",
			"blockEndTimer",
		];
	}
	/**
	 * List of API ressources that may write php session data
	 *
	 * @return array
	 */
	protected function getRessourcesWithSessionWrite(): array
	{
		return [
			"initOauth",
			"firebaseOauth",
			"ticketRedirect",
		];
	}

	public function call()
	{

		$this->request_uri  = $_SERVER['REQUEST_URI'];
		$this->verb         = $_SERVER['REQUEST_METHOD'];
		$path_info          = (isset($_SERVER['PATH_INFO'])) ? str_replace("api/", "", $_SERVER['PATH_INFO']) : '';
		$path_info          = str_replace('v1/', '', $path_info);
        $path_info          = trim($path_info, '/');
		$this->url_elements = explode('/', $path_info);

		// retrieve requested resource
		$resource      = trim(strval($this->url_elements[0]));
		$is_inline_doc = (strlen($resource) == 0) || ($resource == "api");

		// Add headers for CORS
		$this->cors();

		// retrieve paramaters (in body, query_string, headers)
		$this->parseIncomingParams($is_inline_doc);

		// show debug if required
		if (isset($this->parameters['debug'])) {
			$this->debug = $this->parameters['debug'];
			if (empty($this->debug)) {
				$this->debug = 1;
			}
		}

		// retrieve session (if exist)
		$this->retrieveSession();
		$this->initApi();
		$this->manageUploadedFiles();

		// retrieve param who permit session writing
		if (isset($this->parameters['session_write'])) {
			$this->session_write = (bool)$this->parameters['session_write'];
		}

		// Do not unlock the php session for ressources that may handle it
		if (in_array($resource, $this->getRessourcesWithSessionWrite())) {
			$this->session_write = true;
		}

		// Check API session unless blacklisted (init session, ...)
		if (!$is_inline_doc && !in_array($resource, $this->getRessourcesAllowedWithoutSession())) {
			//$this->initEndpoint(true, $resource);
			// pass all the endpoint to initEndpoint so it logs like resource/function, not only resource
			$this->initEndpoint(true, $path_info);
		}

		$this->pluginActivated();

		switch ($resource) {
			case 'planningTask':
				return $this->returnResponse($this->planningTask($this->parameters));
				break;
			case 'forceEndTimer':
				return $this->returnResponse($this->forceEndTimer($this->parameters));
				break;
			case 'blockEndTimer':
				return $this->returnResponse($this->blockEndTimer($this->parameters));
				break;
			case 'pluginStatus':
				return $this->returnResponse($this->pluginStatus($this->parameters));
				break;
			case 'ticketPluginInfo':
				return $this->returnResponse($this->ticketPluginInfo($this->parameters));
				break;
			case 'itilPluginInfo':
				return $this->returnResponse($this->itilPluginInfo($this->parameters));
				break;
			default:
				$path = str_replace($resource . "/", "", trim($path_info, '/'));
				$this->url_elements = explode('/', $path);
				$function = trim(strval($this->url_elements[0]));
				switch ($resource) {
					case 'actualtime':
						$this->pluginActivated($resource);
						switch ($function) {
							case 'startTimer':
								return $this->returnResponse($this->startTimer($this->parameters));
								break;
							case 'overrideStart':
								return $this->returnResponse($this->overrideStart($this->parameters));
								break;
							case 'pauseTimer':
								return $this->returnResponse($this->pauseTimer($this->parameters));
								break;
							case 'stopTimer':
								return $this->returnResponse($this->stopTimer($this->parameters));
								break;
							case 'overrideStop':
								return $this->returnResponse($this->overrideStop($this->parameters));
								break;
							case 'statsTimer':
								return $this->returnResponse($this->statsTimer($this->parameters));
								break;
							case 'timerStatus':
								return $this->returnResponse($this->timerStatus($this->parameters));
								break;
							case 'assetTasksTimer':
								return $this->returnResponse($this->assetTasksTimer($this->parameters));
								break;
							case 'runningTimers':
								return $this->returnResponse($this->runningTimers($this->parameters));
								break;
							default:
								$this->messageLostError();
								break;
						}
						break;
					default:
						$this->messageLostError();
						break;
				}
				break;
		}
	}

	public function returnResponse($response, $httpcode = 200, $additionalheaders = [])
	{
		if (empty($httpcode)) {
			$httpcode = 200;
		}

		foreach ($additionalheaders as $key => $value) {
			header("$key: $value");
		}

		http_response_code($httpcode);
		$this->header($this->debug);

		if ($response !== null) {
			$json = json_encode($response, JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_NUMERIC_CHECK
				| ($this->debug
					? JSON_PRETTY_PRINT
					: 0));
		} else {
			$json = '';
		}

		if ($this->debug) {
			echo "<pre>";
			var_dump($response);
			echo "</pre>";
		} else {
			echo $json;
		}
		exit;
	}

	/**
     * Generic function to send a error message and an error code to client
     *
     * @param string  $message         message to send (human readable)(default 'Bad Request')
     * @param integer $httpcode        http code (see : https://en.wikipedia.org/wiki/List_of_HTTP_status_codes)
     *                                      (default 400)
     * @param string  $statuscode      API status (to represent more precisely the current error)
     *                                      (default ERROR)
     * @param boolean $docmessage      if true, add a link to inline document in message
     *                                      (default true)
     * @param boolean $return_response if true, the error will be send to returnResponse function
     *                                      (who may exit after sending data), otherwise,
     *                                      we will return an array with the error
     *                                      (default true)
     *
     * @return array|void
     */
    public function returnError(
        $message = "Bad Request",
        $httpcode = 400,
        $statuscode = "ERROR",
        $docmessage = true,
        $return_response = true
    ) {

        if (empty($httpcode)) {
            $httpcode = 400;
        }
        if (empty($statuscode)) {
            $statuscode = "ERROR";
        }

        if ($docmessage) {
            $message .= "; " . sprintf(
                __("view documentation in your browser at %s"),
                self::$api_url . "/#$statuscode"
            );
        }

        if ($return_response) {
            $this->returnResponse([$statuscode, $message], $httpcode);
        }
        return [$statuscode, $message];
    }


	protected function planningTask($params = [])
	{
		global $DB;

		if (!isset($params['date'])) {
			$this->returnError();
		}
		$user_id = Session::getLoginUserID();
		$info['listTask'] = [];
		$query = [
			'SELECT' => [
				'glpi_tickettasks.id AS task_id',
				'glpi_tickettasks.content AS task_content',
				'glpi_taskcategories.name AS task_category',
				'glpi_tickettasks.taskcategories_id AS task_category_id',
				'glpi_tickettasks.begin AS task_begin_date',
				'glpi_tickettasks.end AS task_end_date',
				'glpi_tickettasks.state AS task_state',
				'glpi_tickets.id AS ticket_id',
				'glpi_tickets.type AS ticket_type',
				'glpi_locations.name AS ticket_location',
				'glpi_locations.longitude AS ticket_location_longitude',
				'glpi_locations.latitude AS ticket_location_latitude',
				'glpi_tickets.locations_id AS ticket_location_id',
				'glpi_entities.name AS ticket_entity',
				'glpi_entities.completename AS entity_completename',
				'glpi_tickets.entities_id AS ticket_entity_id',
				'glpi_tickets.name AS ticket_title',
			],
			'FROM' => 'glpi_tickettasks',
			'INNER JOIN' => [
				'glpi_tickets' => [
					'ON' => [
						'glpi_tickettasks' => 'tickets_id',
						'glpi_tickets' => 'id'
					]
				],
				'glpi_entities' => [
					'ON' => [
						'glpi_tickets' => 'entities_id',
						'glpi_entities' => 'id'
					]
				]
			],
			'LEFT JOIN' => [
				'glpi_taskcategories' => [
					'ON' => [
						'glpi_tickettasks' => 'taskcategories_id',
						'glpi_taskcategories' => 'id'
					]
				],
				'glpi_locations' => [
					'ON' => [
						'glpi_tickets' => 'locations_id',
						'glpi_locations' => 'id'
					]
				]
			],
			'WHERE' => [
				'glpi_tickettasks.users_id_tech' => $user_id,
				'glpi_tickettasks.begin' => ['LIKE', $params['date'] . "%"],
			]
		];
		$plugin = new Plugin();

		if (isset($params['endDate'])) {
			$query['WHERE']['glpi_tickettasks.begin'] = ['>', $params['date']];
			$query['WHERE']['AND']['glpi_tickettasks.begin'] = ['<', $params['endDate'] . " 23:59:59"];
		}

		if ($result = $DB->request($query)) {
			foreach ($result as $data) {
				$info['listTask'][] = $data;
			}
		}

		$info['lastTimer']['actualtime'] = null;
		if ($plugin->isActivated('actualtime')) {
			$date = date('Y-m-d');
			//actualtime
			$subquery = new \QuerySubQuery([
				'SELECT' => [
					'MAX' => 'actual_begin AS actual_begin',
				],
				'FROM' => PluginActualtimeTask::getTable(),
				'WHERE' => [
					'users_id' => $user_id,
					'actual_begin' => ['LIKE', '%' . $date . '%']
				]
			]);
			$query = [
				'FROM' => PluginActualtimeTask::getTable(),
				'WHERE' => [
					'actual_begin' => $subquery
				]
			];
			$req = $DB->request($query);
			if ($row = $req->current()) {
				$info['lastTimer']['actualtime'] = $row;
			}
		}

		$info['tech_location'] = null;
		$query = [
			'SELECT' => [
				'glpi_locations.latitude',
				'glpi_locations.longitude',
				'glpi_locations.name',
				'glpi_locations.id'
			],
			'FROM' => 'glpi_locations',
			'INNER JOIN' => [
				'glpi_users' => [
					'ON' => [
						'glpi_users' => 'locations_id',
						'glpi_locations' => 'id'
					]
				]
			],
			'WHERE' => [
				'glpi_users.id' => $user_id
			]
		];
		$req = $DB->request($query);
		if ($row = $req->current()) {
			$info['tech_location'] = $row;
		}

		return $info;
	}

	protected function forceEndTimer($params = [])
	{
		global $DB;

		if ($params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		if (!PluginActualtimeTask::checkUserFree(Session::getLoginUserID())) {
			$task_id = PluginActualtimeTask::getTask(Session::getLoginUserID());
			$itemtype = PluginActualtimeTask::getItemtype(Session::getLoginUserID());
			$result = PluginActualtimeTask::stopTimer($task_id, $itemtype, PluginActualtimeTask::ANDROID);

			if ($result['type'] != 'info') {
				$this->returnError($result['message'], 400);
			}

			if ($result['timer_id'] > 0) {
				$geolocation = new PluginGlpimobilextendedGeolocation();
				$input = [
					'date' => date("Y-m-d H:i:s"),
					'users_id' => Session::getLoginUserID(),
					'itemtype' => PluginActualtimeTask::getType(),
					'items_id' => $result['timer_id'],
					'completed' => PluginGlpimobilextendedGeolocation::END,
					'latitude' => $params['latitude'],
					'longitude' => $params['longitude']
				];
				$geolocation->add($input);
			}

			$result = [
				'message' => __("Timer completed", 'actualtime'),
				'title'   => __('Information'),
				'class'   => 'info_msg',
				'segment' => PluginActualtimeTask::getSegment($task_id, $itemtype),
				'time'    => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
			];

			return $result;
		}

		$this->returnError(__("Timer not started", 'actualtime'), 401, 'ERROR_TIMER_NOT_START');
	}

	protected function blockEndTimer($params = [])
	{

		$this->checkAppToken();
		$this->logEndpointUsage(__FUNCTION__);

		$params = self::inputObjectToArray($params['input']);

		if (!isset($params['token'])) {
			$this->returnError();
		}
		$fcmtoken = $params['token'];

		$timer = new PluginGlpimobilextendedTimer();
		if ($timer->getFromDBByCrit(['token' => $fcmtoken])) {
			$timer_id = $timer->getID();
			if ($timer->delete(['id' => $timer_id])) {
				return [$timer_id => true, 'message' => $this->getGlpiLastMessage()];
			}
			$this->returnError([$timer_id => false, 'message' => $this->getGlpiLastMessage()], 400, "ERROR_GLPI_DELETE", false);
		}
		$this->returnError([0 => false, 'message' => __("Item not found")], 400, "ERROR_GLPI_DELETE", false);
	}

	protected function pluginStatus($params = [])
	{
		$data['actualtime'] = self::timerStatus($params);
		return $data;
	}

	protected function ticketPluginInfo($params = [])
	{
		global $DB;

		if (!isset($params['tickets_id'])) {
			$this->returnError();
		}

		$ticket = new Ticket();
		if (!$ticket->getFromDB($params['tickets_id'])) {
			$this->messageNotfoundError();
		}

		$listfollowups = [];
		$followup_obj = new ITILFollowup();
		$followups = $followup_obj->find(['items_id'  => $ticket->getID(), 'itemtype' => $ticket::getType()], ['date DESC', 'id DESC']);
		foreach ($followups as $followups_id => $followup) {
			$listfollowups[] = $followups_id;
		}

		$listtasks = [];
		$task_obj = new TicketTask();
		$tasks = $task_obj->find(['tickets_id'  => $ticket->getID()], ['date DESC', 'id DESC']);
		foreach ($tasks as $tasks_id => $task) {
			$listtasks[] = $tasks_id;
		}

		$plugin = new Plugin();

		$data = [
			'actualtime' => [],
			'costs' => [],
		];

		if ($plugin->isActivated('actualtime')) {
			$itemtype = TicketTask::getType();
			if (isset($params['itemtype'])) {
				$itemtype = $params['itemtype'];
			}
			$data['actualtime']['tasks'] = [];
			if (count($listtasks) > 0) {
				foreach ($listtasks as $key => $value) {
					$data['actualtime']['tasks'][] = [
						'id' => $value,
						'timer_active' => PluginActualtimeTask::checkTimerActive($value, $itemtype),
						'time' => abs(PluginActualtimeTask::totalEndTime($value, $itemtype)),
					];
				}
			}
		}

		if ($plugin->isActivated('costs')) {
			$data['costs']['ticket'] = PluginCostsTicket::isBillable($ticket->getID());
			if (count($listtasks) > 0) {
				$query = [
					'FROM' => PluginCostsTask::getTable(),
					'WHERE' => [
						'tasks_id' => $listtasks
					]
				];
				$iterator = $DB->request($query);
				foreach ($iterator as $row) {
					$data['costs']['tasks'][] = $row;
				}
			}
		}

		return $data;
	}

	protected function itilPluginInfo($params = [])
	{
		global $DB;

		if (!isset($params['items_id']) || !isset($params['itemtype'])) {
			$this->returnError();
		}

		$item = new $params['itemtype']();
		if (!$item->getFromDB($params['items_id'])) {
			$this->messageNotfoundError();
		}

		$listfollowups = [];
		$followup_obj = new ITILFollowup();
		$followups = $followup_obj->find(['items_id'  => $item->getID(), 'itemtype' => $item::getType()], ['date DESC', 'id DESC']);
		foreach ($followups as $followups_id => $followup) {
			$listfollowups[] = $followups_id;
		}

		$listtasks = [];
		$itemtask = $item->getTaskClass();
		$taskfield = $item->getForeignKeyField();
		$task_obj = new $itemtask();
		$tasks = $task_obj->find([$taskfield  => $item->getID()], ['date DESC', 'id DESC']);
		foreach ($tasks as $tasks_id => $task) {
			$listtasks[] = $tasks_id;
		}

		$plugin = new Plugin();

		$data = [
			'actualtime' => [],
			'costs' => [],
		];

		if ($plugin->isActivated('actualtime')) {
			$data['actualtime']['tasks'] = [];
			if (count($listtasks) > 0) {
				foreach ($listtasks as $key => $value) {
					$data['actualtime']['tasks'][] = [
						'id' => $value,
						'timer_active' => PluginActualtimeTask::checkTimerActive($value, $itemtask),
						'time' => abs(PluginActualtimeTask::totalEndTime($value, $itemtask)),
					];
				}
			}
		}

		if ($plugin->isActivated('costs') && $item->getType() == 'Ticket') {
			$data['costs']['ticket'] = PluginCostsTicket::isBillable($item->getID());
			if (count($listtasks) > 0) {
				$query = [
					'FROM' => PluginCostsTask::getTable(),
					'WHERE' => [
						'tasks_id' => $listtasks
					]
				];
				$iterator = $DB->request($query);
				foreach ($iterator as $row) {
					$data['costs']['tasks'][] = $row;
				}
			}
		}

		return $data;
	}


	//Plugin Actualtime
	protected function startTimer($params = [])
	{
		$task_id = $this->getId();

		if (!isset($params['latitude']) || !isset($params['longitude']) || $params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		$itemtype = TicketTask::getType();
		if (isset($params['itemtype'])) {
			$itemtype = $params['itemtype'];
		}

		$result = PluginActualtimeTask::startTimer($task_id, $itemtype, PluginActualtimeTask::ANDROID);


		if ($result['type'] != 'info') {
			$this->returnError($result['message'], 400);
		}

		$geolocation = new PluginGlpimobilextendedGeolocation();
		$input = [
			'date' => date("Y-m-d H:i:s"),
			'users_id' => Session::getLoginUserID(),
			'itemtype' => PluginActualtimeTask::getType(),
			'items_id' => $result['timer_id'],
			'completed' => PluginGlpimobilextendedGeolocation::START,
			'latitude' => $params['latitude'],
			'longitude' => $params['longitude']
		];
		$geolocation->add($input);

		$response = [
			'message'   => __("Timer started", 'actualtime'),
			'time'      => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
		];

		return $response;
	}

	protected function overrideStart($params = [])
	{
		global $DB;

		if (!isset($params['task_id']) || !isset($params['date_start']) || !isset($params['itemtype'])) {
			$this->returnError();
		}

		if (!isset($params['latitude']) || !isset($params['longitude']) || $params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		$task = new $params['itemtype']();
		if (!$task->getFromDB($params['task_id'])) {
			$this->returnError(__("Item not found"), 400, 'ERROR_ITEM_NOT_FOUND');
		}

		if (isset($task->fields['state'])) {
			if ($task->getField('state') != 1) {
				$this->returnResponse(__("Task completed."), 409);
			}
		} else {
			$finished_states_it = $DB->request(
				[
					'SELECT' => ['id'],
					'FROM'   => ProjectState::getTable(),
					'WHERE'  => [
						'is_finished' => 1
					],
				]
			);
			$finished_states_ids = [];
			foreach ($finished_states_it as $finished_state) {
				$finished_states_ids[] = $finished_state['id'];
			}
			if (in_array($task->getField('projectstates_id'), $finished_states_ids)) {
				$this->returnResponse(__("Task completed."), 409);
			}
		}

		if (isset($task->fields['users_id_tech'])) {
			if (Session::getLoginUserID() != $task->fields['users_id_tech']) {
				$this->returnResponse(__("Technician not in charge of the task", 'actualtime'), 403);
			}
		} else {
			if (!$task->canUpdateItem()) {
				$this->returnResponse(__("Technician not in charge of the task", 'actualtime'), 403);
			}
		}

		if (PluginActualtimeTask::checkTimerActive($params['task_id'], $params['itemtype'])) {
			$this->returnResponse(__("A user is already performing the task", 'actualtime'), 409);
		} else {
			if (!PluginActualtimeTask::checkUserFree(Session::getLoginUserID())) {
				if (is_a($task, CommonDBChild::class, true)) {
					$parent = getItemForItemtype($task::$itemtype);
				} else {
					$parent = getItemForItemtype($task->getItilObjectItemType());
				}
				$parent_key = $parent->getForeignKeyField();
				$parent_id = $task->fields[$parent_key];
				$url = $parent->getFormURLWithID($parent_id);

				$iterator = $DB->request([
					'FROM' => $itemtype::getTable(),
					'WHERE' => [$parent_key => $parent_id]
				]);

				$active_task = '';
				foreach ($iterator as $parenttask) {
					if (PluginActualtimeTask::checkTimerActive($parenttask['id'], $itemtype)) {
						$active_task = $parenttask['id'];
						break;
					}
				}

				$message = sprintf(__('You are already working on %s', 'actualtime'), $parent::getTypeName(1));
				$link = '<a href="' . $url . '">#' . $parent_id . '</a>';
				$message .= ' ' . $link;
				if ($active_task != '') {
					$message .= ' (' . __('Task') . ' #' . $active_task . ')';
				}
				$this->returnResponse($message, 409);
			} else {
				$input = [
					'items_id' => $params['task_id'],
					'itemtype' => $params['itemtype'],
					'actual_begin' => $params['date_start'],
					'users_id' => Session::getLoginUserID(),
					'origin_start' => PluginActualtimeTask::ANDROID,
					'override_begin' => date("Y-m-d H:i:s"),
				];
				$actualtime = new PluginActualtimeTask();
				$ID = $actualtime->add($input);

				$geolocation = new PluginGlpimobilextendedGeolocation();
				$input = [
					'date' => $params['date_start'],
					'users_id' => Session::getLoginUserID(),
					'itemtype' => PluginActualtimeTask::getType(),
					'items_id' => $ID,
					'completed' => PluginGlpimobilextendedGeolocation::START,
					'latitude' => $params['latitude'],
					'longitude' => $params['longitude']
				];
				$geolocation->add($input);

				$result = [
					'message'   => __("Timer started", 'actualtime'),
					'time'      => abs(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype'])),
					'timer_id'  => $ID
				];
			}
		}

		return $result;
	}

	protected function pauseTimer($params = [])
	{
		$task_id = $this->getId();

		if (!isset($params['latitude']) || !isset($params['longitude']) || $params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		$itemtype = TicketTask::getType();
		if (isset($params['itemtype'])) {
			$itemtype = $params['itemtype'];
		}

		$result = PluginActualtimeTask::pauseTimer($task_id, $itemtype, PluginActualtimeTask::ANDROID);

		if ($result['type'] != 'info') {
			$this->returnError($result['message'], 400);
		}

		$geolocation = new PluginGlpimobilextendedGeolocation();
		$input = [
			'date' => date("Y-m-d H:i:s"),
			'users_id' => Session::getLoginUserID(),
			'itemtype' => PluginActualtimeTask::getType(),
			'items_id' => $result['timer_id'],
			'completed' => PluginGlpimobilextendedGeolocation::END,
			'latitude' => $params['latitude'],
			'longitude' => $params['longitude']
		];
		$geolocation->add($input);

		$response = [
			'message' => __("Timer completed", 'actualtime'),
			'title'   => __('Information'),
			'class'   => 'info_msg',
			'segment' => PluginActualtimeTask::getSegment($task_id, $itemtype),
			'time'    => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
		];

		return $response;
	}

	protected function stopTimer($params = [])
	{
		Toolbox::logInFile('gappxtended-debug', 'STOPTIMER ACTUALTIME CALL: ' . print_r($params, true) . PHP_EOL);

		$task_id = $this->getId();

		if (!isset($params['latitude']) || !isset($params['longitude']) || $params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		$itemtype = TicketTask::getType();
		if (isset($params['itemtype'])) {
			$itemtype = $params['itemtype'];
		}

		$result = PluginActualtimeTask::stopTimer($task_id, $itemtype, PluginActualtimeTask::ANDROID);

		if ($result['type'] != 'info') {
			$this->returnError($result['message'], 400);
		}

		if ($result['timer_id'] > 0) {
			$geolocation = new PluginGlpimobilextendedGeolocation();
			$input = [
				'date' => date("Y-m-d H:i:s"),
				'users_id' => Session::getLoginUserID(),
				'itemtype' => PluginActualtimeTask::getType(),
				'items_id' => $result['timer_id'],
				'completed' => PluginGlpimobilextendedGeolocation::END,
				'latitude' => $params['latitude'],
				'longitude' => $params['longitude']
			];
			$geolocation->add($input);
		}

		$task = new TicketTask();
		$task->getFromDB($task_id);

		if (isset($task->fields['actiontime'])) {
			$actiontime = $task->getField('actiontime');
		} else {
			$actiontime = $task->getField('effective_duration');
		}

		$response = [
			'message' => __("Timer completed", 'actualtime'),
			'segment' => PluginActualtimeTask::getSegment($task_id, $itemtype),
			'time'    => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype)),
			'task_time' => $actiontime,
		];
		return $response;
	}

	protected function overrideStop($params = [])
	{
		global $CFG_GLPI;

		if (!isset($params['task_id']) || !isset($params['date_end']) || !isset($params['itemtype'])) {
			$this->returnError();
		}

		if (!isset($params['latitude']) || !isset($params['longitude']) || $params['latitude'] == 0 || $params['longitude'] == 0) {
			$this->returnError(__("Geolocation is mandatory", 'actualtime'), 400, "ERROR_GEOLOCATION");
		}

		$config = new PluginActualtimeConfig;

		if (PluginActualtimeTask::checkTimerActive($params['task_id'], $params['itemtype'])) {
			if (PluginActualtimeTask::checkUser($params['task_id'], $params['itemtype'], Session::getLoginUserID())) {

				$actual_begin = PluginActualtimeTask::getActualBegin($params['task_id'], $params['itemtype']);
				$seconds = (strtotime($params['date_end']) - strtotime($actual_begin));
				$actualtime = new PluginActualtimeTask();
				$actualtime->getFromDBByCrit([
					'items_id' => $params['task_id'],
					'itemtype' => $params['itemtype'],
					[
						'NOT' => ['actual_begin' => null],
					],
					'actual_end' => null,
				]);
				$timer_id = $actualtime->getID();

				$input = [
					'actual_end'        => $params['date_end'],
					'actual_actiontime' => $seconds,
					'origin_end' => PluginActualtimetask::ANDROID,
					'id' => $timer_id,
					'override_end' => date("Y-m-d H:i:s"),
				];
				$actualtime->update($input);

				$geolocation = new PluginGlpimobilextendedGeolocation();
				$input = [
					'date' => date("Y-m-d H:i:s"),
					'users_id' => Session::getLoginUserID(),
					'itemtype' => PluginActualtimeTask::getType(),
					'items_id' => $timer_id,
					'completed' => PluginGlpimobilextendedGeolocation::END,
					'latitude' => $params['latitude'],
					'longitude' => $params['longitude']
				];
				$geolocation->add($input);

				$input = [];
				$task = new $params['itemtype']();
				$task->getFromDB($params['task_id']);
				$input['id'] = $params['task_id'];
				$input['state'] = 2;
				if ($config->autoUpdateDuration()) {
					if (isset($task->fields['actiontime'])) {
						$input['actiontime'] = ceil(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype']) / ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP)) * ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP);
					} else {
						$input['effective_duration'] = ceil(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype']) / ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP)) * ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP);
					}
				}
				$task->update($input);

				if (isset($task->fields['actiontime'])) {
					$actiontime = $task->getField('actiontime');
				} else {
					$actiontime = $task->getField('effective_duration');
				}

				$result = [
					'message' => __("Timer completed", 'actualtime'),
					'segment' => PluginActualtimeTask::getSegment($params['task_id'], $params['itemtype']),
					'time'    => abs(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype'])),
					'task_time' => $actiontime,
				];
			} else {
				$this->returnResponse(__("Only the user who initiated the task can close it", 'actualtime'), 409);
			}
		} else {
			$task = new $params['itemtype']();
			$task->getFromDB($params['task_id']);
			$input['id'] = $params['task_id'];
			$input['state'] = 2;
			if ($config->autoUpdateDuration()) {
				if (isset($task->fields['actiontime'])) {
					$input['actiontime'] = ceil(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype']) / ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP)) * ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP);
				} else {
					$input['effective_duration'] = ceil(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype']) / ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP)) * ($CFG_GLPI["time_step"] * MINUTE_TIMESTAMP);
				}
			}
			$task->update($input);

			if (isset($task->fields['actiontime'])) {
				$actiontime = $task->getField('actiontime');
			} else {
				$actiontime = $task->getField('effective_duration');
			}

			$result = [
				'message' => __("Timer completed", 'actualtime'),
				'segment' => PluginActualtimeTask::getSegment($params['task_id'], $params['itemtype']),
				'time'    => abs(PluginActualtimeTask::totalEndTime($params['task_id'], $params['itemtype'])),
				'task_time' => $actiontime,
			];
		}
		return $result;
	}

	protected function statsTimer($params = [])
	{
		global $DB;

		$task_id = $this->getId();

		$itemtype = TicketTask::getType();
		if (isset($params['itemtype'])) {
			$itemtype = $params['itemtype'];
		}

		$query = [
			'FROM' => $itemtype::getTable(),
			'WHERE' => [
				'id' => $task_id,
			]
		];
		$req = $DB->request($query);
		$actiontime = 0;
		if ($row = $req->current()) {
			if (isset($row['actiontime'])) {
				$actiontime = $row['actiontime'];
			} else {
				$actiontime = $row['effective_duration'];
			}
		}
		$actual_totaltime = abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype));
		if ($actiontime == 0) {
			$diffpercent = 0;
		} else {
			$diffpercent = 100 * ($actiontime - $actual_totaltime) / $actiontime;
		}
		$result = [
			'time' => $actual_totaltime,
			'actiontime' => $actiontime,
			'diff' => $actiontime - $actual_totaltime,
			'diffpercent' => $diffpercent,
		];

		return $result;
	}

	protected function timerStatus($params = [])
	{

		if (PluginActualtimeTask::checkUserFree(Session::getLoginUserID())) {
			$result = [
				'free' => true,
			];
			if (isset($params['task_id'])) {
				$itemtype = TicketTask::getType();
				if (isset($params['itemtype'])) {
					$itemtype = $params['itemtype'];
				}
				$result['time'] = abs(PluginActualtimeTask::totalEndTime($params['task_id'], $itemtype));
			}
		} else {
			$itemtype = PluginActualtimeTask::getItemtype(Session::getLoginUserID());
			$task = getItemForItemtype($itemtype);
			$task_id = PluginActualtimeTask::getTask(Session::getLoginUserID());
			$task->getFromDB($task_id);
			if (is_a($task, CommonDBChild::class, true)) {
				$parent = getItemForItemtype($task::$itemtype);
			} else {
				$parent = getItemForItemtype($task->getItilObjectItemType());
			}
			$parent_key = $parent->getForeignKeyField();
			$parent_id = $task->fields[$parent_key];
			$parent->getFromDB($parent_id);

			$result = [
				'free' => false,
				'itemtype' => $parent->getType(),
				'parent_id' => $parent_id,
				'ticket_id' => $parent_id,
				'name' => $parent->fields['name'],
				'task_id' => $task_id,
				'time' => abs(PluginActualtimeTask::totalEndTime($task_id, $itemtype))
			];
		}
		return $result;
	}

	protected function assetTasksTimer($params = [])
	{
		global $DB;

		if (!isset($params['itemtype']) || !isset($params['items_id'])) {
			$this->returnError();
		}

		if (!isset($params['add_keys_names'])) {
			$params['add_keys_names'] = [];
		}

		$itemtype = $params['itemtype'];
		$items_id = $params['items_id'];

		$result = [];

		$query = [
			'SELECT' => [
				'itemtype'
			],
			'DISTINCT' => true,
			'FROM' => PluginActualtimeTask::getTable(),
		];
		foreach ($DB->request($query) as $id => $rowtype) {
			$tasktype = $rowtype['itemtype'];

			$task = new $tasktype();

			if (is_a($task, CommonDBChild::class, true)) {
				$parent = getItemForItemtype($task::$itemtype);
			} else {
				$parent = getItemForItemtype($task->getItilObjectItemType());
			}
			if (is_a($parent, CommonITILObject::class, true)) {
				$item_link = getItemForItemtype($parent->getItemLinkClass());
			} else {
				$item_link = new Item_Project();
			}
			$tasktable = $task::getTable();
			$parenttable = $parent::getTable();
			$itemtable = $item_link::getTable();
			$parentkey = $parent->getForeignKeyField();

			$sql = [
				'SELECT' => [
					$tasktable . '.*'
				],
				'DISTINCT' => true,
				'FROM' => $tasktable,
				'INNER JOIN' => [
					$parenttable => [
						'ON' => [
							$parenttable => 'id',
							$tasktable => $parentkey
						]
					],
					$itemtable => [
						'ON' => [
							$itemtable => $parentkey,
							$parenttable => 'id',
						]
					],
				],
				'WHERE' => [
					$itemtable . '.itemtype' => $itemtype,
					$itemtable . '.items_id' => $items_id,
				]
			];

			if (isset($task->fields['state'])) {
				$sql['WHERE'][$tasktable . '.state'] = 1;
			} else {
				$finished_states_it = $DB->request(
					[
						'SELECT' => ['id'],
						'FROM'   => ProjectState::getTable(),
						'WHERE'  => [
							'is_finished' => 1
						],
					]
				);
				$finished_states_ids = [];
				foreach ($finished_states_it as $finished_state) {
					$finished_states_ids[] = $finished_state['id'];
				}
				$sql['WHERE'][$tasktable . '.projectstates_id'] = $finished_states_ids;
			}

			if (isset($task->fields['users_id_tech'])) {
				if (isset($_SESSION['glpigroups']) && count($_SESSION['glpigroups']) > 0) {
					$sql['WHERE']['OR'] = [
						$tasktable . '.users_id_tech' => Session::getLoginUserID(),
						$tasktable . '.groups_id_tech' => $_SESSION['glpigroups'],
					];
				} else {
					$sql['WHERE'][$tasktable . '.users_id_tech'] = Session::getLoginUserID();
				}
			} else {
				$project_visibility = Project::getVisibilityCriteria();
				$sql['LEFT JOIN'] =  $project_visibility['LEFT JOIN'];
				$sql['WHERE'] += $project_visibility['WHERE'];
			}

			$add_keys_names = count($params['add_keys_names']) > 0;

			$result = [];
			foreach ($DB->request($sql) as $id => $row) {
				if ($add_keys_names) {
					// Insert raw names into the data row
					$row["_keys_names"] = $this->getFriendlyNames(
						$row,
						$params,
						$tasktype
					);
				}
				$row['actualtime'] = abs(PluginActualtimeTask::totalEndTime($row['id'], $tasktype));

				$result[] = $row;
			}
		}

		return $result;
	}

	protected function runningTimers($params = [])
	{
		global $DB;

		$timers = [];
		$query = [
			'FROM' => PluginActualtimeTask::getTable(),
			'WHERE' => [
				'NOT' => ['actual_begin' => null],
				'actual_end' => null,
			]
		];
		foreach ($DB->request($query) as $id => $actualtime) {
			$tasktype = $actualtime['itemtype'];

			$task = new $tasktype();
			$task->getFromDB($actualtime['items_id']);

			if (is_a($task, CommonDBChild::class, true)) {
				$parent = getItemForItemtype($task::$itemtype);
			} else {
				$parent = getItemForItemtype($task->getItilObjectItemType());
			}
			$parentkey = $parent->getForeignKeyField();

			$parent->getFromDB($task->fields[$parentkey]);

			$timer = [
				'tech_id' => $actualtime['users_id'],
				'tech_name' => getUserName($actualtime['users_id']),
				'entity_id' => $parent->fields['entities_id'],
				'entity_name' => Dropdown::getDropdownName(Entity::getTable(), $parent->fields['entities_id']),
				'parent_id' => $parent->fields['id'],
				'parent_itemtype' => $parent->getType(),
				'task_id' => $actualtime['items_id'],
				'task_itemtype' => $actualtime['itemtype'],
				'parent_name' => $parent->fields['name'],
				'actualtime' => PluginActualtimeTask::totalEndTime($actualtime['items_id'], $actualtime['itemtype']),
				'parent_location_id' => 0,
				'parent_location_name' => '',
				'parent_location_latitude' => '',
				'parent_location_longitude' => '',
			];

			if (isset($parent->fields['locations_id']) && $parent->fields['locations_id'] > 0) {
				$location = new Location();
				$location->getFromDB($parent->fields['locations_id']);
				$timer['parent_location_id'] = $location->getID();
				$timer['parent_location_name'] = $location->fields['name'];
				$timer['parent_location_latitude'] = $location->fields['latitude'];
				$timer['parent_location_longitude'] = $location->fields['longitude'];
			}
			$items = [];

			if (is_a($parent, CommonITILObject::class, true)) {
				$item_link = getItemForItemtype($parent->getItemLinkClass());
			} else {
				$item_link = new Item_Project();
			}
			$types_iterator = $item_link::getDistinctTypes($parent->fields['id']);
			foreach ($types_iterator as $type) {
				$itemtype = $type['itemtype'];
				if (!($item = getItemForItemtype($itemtype))) {
					continue;
				}
				$iterator = $item_link::getTypeItems($parent->fields['id'], $itemtype);
				foreach ($iterator as $data) {
					$data['itemtype'] = $itemtype;
					$items[] = $data;
				}
			}
			$timer['items'] = $items;
			$timers[] = $timer;
		}

		return $timers;
	}
}
