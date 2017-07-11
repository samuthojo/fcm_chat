<?php

error_reporting(-1);
ini_set('display_errors', 'On');
 
require_once '../include/db_handler.php';
require '../libs/Slim/Slim/Slim.php';
 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
 
// User login
$app->post('/user/login', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('username', 'password'));
 
    // reading post params
    $username = $app->request->post('username');
    $password = $app->request->post('password');
 
    $db = new DbHandler();
    $response = $db->loginUser($username, $password);
 
    // echo json response
    echoRespnse(200, $response);
});
 
 
/* * *
 * Updating user
 *  we use this url to update user's fcm registration id
 */
$app->put('/user/:id', function($user_id) use ($app) {
    global $app;
 
    verifyRequiredParams(array('fcm_registration_id'));
 
    $fcm_registration_id = $app->request->put('fcm_registration_id');
 
    $db = new DbHandler();
    $response = $db->updateFcmID($user_id, $fcm_registration_id);
 
    echoRespnse(200, $response);
});

// Check the existence of a class for a given service
$app->post('/class', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('date', 'class', 'service'));
 
    // reading post params
    $date = $app->request->post('date');
    $class = $app->request->post('class');
    $service = $app->request->post('service');

    $db = new DbHandler();
    $response = $db->checkClass($date, $class, $service);
 
    // echo json response
    echoRespnse(200, $response);
});

// Submit attendance to the database
$app->post('/roster', function() use ($app) {
    $db = new DbHandler();
    //Check for required params
    verifyRequiredParams(array('date', 'service', 'class', 'teacher_on_duty', 
        'males', 'females', 'timers', 'converts', 'teachers'));
    //reading post params
    $date = $app->request->post('date');
    $service = $app->request->post('service');
    $class = $app->request->post('class');
    $on_duty = $app->request->post('teacher_on_duty');
    $males = $app->request->post('males');
    $females = $app->request->post('females');
    $timers = $app->request->post('timers');
    $converts = $app->request->post('converts');
    $teachers = $app->request->post('teachers');
        
    //submit data to database
    if(strcasecmp($service, "first") === 0) {
        $response = $db->submitFirstServiceData($date, $class, $on_duty, $males, $females,
                                                $timers, $converts, $teachers);
    } else {
        $response = $db->submitSecondServiceData($date, $class, $on_duty, $males, $females,
                                                 $timers, $converts, $teachers);
    }
    echoRespnse(200, $response);
});

/* * *
 * fetching all advice_topics
 */
$app->get('/advice_topics', function() {
    $response = array();
    $db = new DbHandler();
 
    // fetching all user tasks
    $result = $db->getAllAdviceTopics();
 
    $response["error"] = false;
    $response["advice_topics"] = array();
 
    // pushing single chat room into array
    while ($advice_topic = $result->fetch()) {
        array_push($response["advice_topics"], $advice_topic);
    }
 
    echoRespnse(200, $response);
});
 
/**
 * Messaging in a topic
 * Will send push notification using Topic Messaging
 *  
 */
$app->post('/advice_topics/:id/advice', function($topic_id) {
    global $app;
    $db = new DbHandler();
 
    verifyRequiredParams(array('user_id', 'advice'));
 
    $giver_id = $app->request->post('user_id');
    $content = $app->request->post('advice');
 
    $response = $db->addAdvice($content, $topic_id, $giver_id);
 
    if ($response['error'] == false) {
        require_once __DIR__ . '/../libs/fcm/fcm.php';
        require_once __DIR__ . '/../libs/fcm/push.php';
        $fcm = new FCM();
        $push = new Push();
 
        // get the user using userid
        $user = $db->getUser($giver_id);
 
        $data = array();
        $data['user'] = $user;
        $data['message'] = $response['message'];
        $data['topic_id'] = $topic_id;
 
        $push->setTitle("Firebase Cloud Messaging");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_ADVICE_TOPIC);
        $push->setData($data);
         
        // echo json_encode($push->getPush());exit;
 
        // sending push message to a topic
        $fcm->sendToTopic('topic_' . $topic_id, $push->getPush());
 
        $response['user'] = $user;
        $response['error'] = false;
    }
 
    echoRespnse(200, $response);
}); 
 
/**
 * Sending push notification to all the teachers
 * We use fcm registration ids to send notification message
 * At max you can send message to 1000 recipients
 * * */
$app->post('/users/message', function() use ($app) {
 
    $response = array();
    verifyRequiredParams(array('user_id', 'message'));
 
    require_once __DIR__ . '/../libs/fcm/fcm.php';
    require_once __DIR__ . '/../libs/fcm/push.php';
 
    $db = new DbHandler();
 
    $user_id = $app->request->post('user_id');
    $message = $app->request->post('message');
    $time_stamp = $app->request()->post('time_stamp');
    $zone_id = $app->request()->post('zone_id');
    
    $user = $db->getUser($user_id);
    $users = $db->getAllTeachers();
 
    $registration_ids = array();
 
    // preparing fcm registration ids array
    foreach ($users as $u) {
        array_push($registration_ids, $u['fcm_id']);
    }
 
    // send push to all teachers
    $fcm = new FCM();
    $push = new Push();
 
    $data = array();
    $data['user'] = $user;
    $data['message'] = $message;
    $data['time_stamp']= $time_stamp;
    $data['zone_id'] = $zone_id;
 
    $push->setTitle("Firebase Cloud Messaging");
    $push->setIsBackground(FALSE);
    $push->setFlag(PUSH_FLAG_CHAT_ROOM);
    $push->setData($data);
 
    // sending push message to multiple users
    $fcm->sendMultiple($registration_ids, $push->getPush());
 
    $response['error'] = false;
 
    echoRespnse(200, $response);
});
 
$app->post('/users/send_to_all', function() use ($app) { 
    $db = new DbHandler();
    
    verifyRequiredParams(array('user_id', 'message'));

    $sender_id = $app->request->post('user_id');
    $message = $app->request->post('message'); 
    
    //$response = $db->addChat($content, $sender_id);

        require_once __DIR__ . '/../libs/fcm/fcm.php';
        require_once __DIR__ . '/../libs/fcm/push.php';
        $fcm = new FCM();
        $push = new Push();
 
        // get the user using userid
        $user = $db->getUser($sender_id);
 
        $data = array();
        $data['user'] = $user;
        $data['message'] = $message;
 
        $push->setTitle("Firebase Cloud Messaging");
        $push->setIsBackground(FALSE);
        $push->setFlag(PUSH_FLAG_CHAT_ROOM);
        $push->setData($data);
     
        // sending message to topic `global`
        // On the device every user should subscribe to `global` topic
        $fcm->sendToTopic('chats', $push->getPush());

        $response['user'] = $user;
        $response['error'] = false;
    
    echoRespnse(200, $response);
});
 
/**
 * Fetches advices for a given advice_topic
 *  
 */
$app->get('/advice_topics/:id', function($advice_topic_id) {
    global $app;
    $db = new DbHandler();
 
    $result = $db->getAdvices($advice_topic_id);
 
    $response["error"] = false;
    $response["messages"] = array();
    $response['chat_room'] = array();
 
    $i = 0;
    // looping through result and preparing tasks array
    while ($advices = $result->fetch()) {
        
          array_push($response["advices"], $advices);
        
    }
 
    echoRespnse(200, $response);
});
 
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
function IsNullOrEmptyString($str) {
    return (!isset($str) || trim($str) === '');
}
 
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json; charset=utf-8');
 
    echo json_encode($response);
}
 
$app->run();

