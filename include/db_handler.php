<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 */
class DbHandler {
 
    private $conn;
 
    function __construct() {
        require_once dirname(__FILE__) . '/db_connect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
 
    //login the user if exists
    public function loginUser($username, $password) {
        $response = array();
 
        // First check if user already existed in db
        if ($this->isUserExists($username, $password)) {            
                $response["error"] = false;
                $response["message"] = "It's okay";
                $res = $this->conn->query("SELECT teacher.id, fullname, mobile, fcm_id, photo_url"
                        . " from teacher, credentials where teacher.id = "
                        . "credentials.teacher_id AND credentials.username = '$username'");
                $response['teacher'] = $res->fetch(PDO::FETCH_ASSOC);
                
            } else {
                $stmt = $this->conn->query("SELECT COUNT(*) from credentials"
                        . " where username = '$username'");
                if($stmt->fetchColumn() > 0) {
                    $response["error"] = true;
                    $response['message'] = "Wrong password";
                } else {
                    $response['error'] = true;
                    $response['message'] = "Username does not exist";
                }
            }
            
        return $response;

    } 
    
    //submit the data to database
    public function submitFirstServiceData($date, $class, $on_duty, $males, $females,
            $timers, $converts, $teachers) {
        
            $stmt = $this->conn->prepare("INSERT INTO first_service (date_text, class, teacher_on_duty, "
                    . "nmales, nfemales, ntimers, nconverts, nteachers) values('$date', '$class', '$on_duty', "
                    . "$males, $females, $timers, $converts, $teachers)");

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response['error'] = false;               
                $response['message'] = "Data recorded successfully"; 
            } else {
                $response['error'] = true;
                $response['message'] = "Please check to correct your data";
            }
        
            return $response;
    }
    
    public function submitSecondServiceData($date, $class, $on_duty, $males, $females,
            $timers, $converts, $teachers) {
            $stmt = $this->conn->prepare("INSERT INTO second_service (date_text, class, teacher_on_duty, "
                    . "nmales, nfemales, ntimers, nconverts, nteachers) values('$date', '$class', '$on_duty', "
                    . "$males, $females, $timers, $converts, $teachers)");

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response['error'] = false;               
                $response['message'] = "Data recorded successfully"; 
            } else {
                $response['error'] = true;
                $response['message'] = "Please check and correct your data";
            }
            
            return $response;
    }
    
    //Check if the class exists for the given service
    public function checkClass($date, $class, $service) {
        if(strcasecmp($service, "first") === 0) {
            $res = $this->conn->query("SELECT COUNT(*) from first_service"
                    . " where date_text = '$date' AND class = '$class'");
            if($res->fetchColumn() > 0) {
                $response['error'] = true;
                $response['message']="The $class data for $service exists!";
            } else {
                $response['error'] = false;
                $response['message']="You may proceed entering the data";
            }
        } else {
            $res = $this->conn->query("SELECT COUNT(*) from second_service"
                    . " where date_text = '$date' AND class = '$class'");
            if($res->fetchColumn() > 0) {
                $response['error'] = true;
                $response['message']="The $class data for $service exists!";
            } else {
                $response['error'] = false;
                $response['message']="You may proceed entering the data";
            }
        }
        return $response;
    } 
 
    // updating user FCM registration ID
    public function updateFcmID($user_id, $fcm_registration_id) {
        $response = array();
        $stmt = $this->conn->prepare("UPDATE teacher SET fcm_id = '$fcm_registration_id' WHERE id = $user_id");
        $stmt->execute();
        $count = $stmt->rowCount();
 
        if ($count > 0) {
            // User successfully updated
            $response["error"] = false;
            $response["message"] = 'FCM registration ID updated successfully';
        } else {
            // Failed to update user
            $response["error"] = true;
            $response["message"] = "Failed to update FCM registration ID";
        }
 
        return $response;
    }
 
    // fetching single user by id
    public function getUser($user_id) {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM teacher WHERE id = $user_id");
        if ($stmt->fetchColumn() > 0) {
            
            $stmt = $this->conn->query("SELECT * FROM teacher WHERE id = $user_id");
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $user;
        } else {
            return NULL;
        }
    }
    
    //fetching all the teachers
    public function getAllTeachers() {
        $stmt = $this->conn->query("SELECT * FROM teacher");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
 
    // fetching multiple users by ids
    public function getUsers($user_ids) {
 
        $users = array();
        if (sizeof($user_ids) > 0) {
            $query = "SELECT * FROM teacher WHERE id IN (";
 
            foreach ($user_ids as $user_id) {
                $query .= $user_id . ',';
            }
 
            $query = substr($query, 0, strlen($query) - 1);
            $query .= ')';
 
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
            while ($user = $result->fetch()) {
                array_push($users, $user);
            }
        }
 
        return $users;
    }
 
    // messaging in a topic 
    public function addAdvice($content, $topic_id, $giver_id) {
        $response = array();
 
        $stmt = $this->conn->prepare("INSERT INTO advice (content, topic_id, giver_id) values(:content, :topic_id, :giver_id)");
        $stmt->bind_param(':content', $content);
        $stmt->bind_param(':topic_id', $topic_id);
        $stmt->bind_param(':giver_id', $giver_id);
 
        $stmt->execute();
 
        if ($stmt->rowCount() > 0) {
            $response['error'] = false;
            $id = $this->conn->lastInsertID();
            $stmt = $this->conn->prepare("SELECT teacher.fullname, advice_topic.topic, advice.content " .
                     "advice.time_sent from advice, advice_topic, teacher where ".
                     "advice.topic_id = advice_topic.id AND advice.giver_id = teacher.id AND advice.id = $id");
            $result = $stmt->execute();
            $message = $result->fetch(PDO::FETCH_ASSOC);
            $response['message'] = $message; 
        } else {
            $response['error'] = true;
            $response['message'] = "Failed to send advice";
        }
 
        return $response;
    }
    
    // messaging in chat_room 
    public function addChat($content, $sender_id) {
        $response = array();
 
        $stmt = $this->conn->prepare("INSERT INTO chat (content, sender_id) values(:content, :sender_id)");
        $stmt->bind_param(':content', $content);
        $stmt->bind_param(':sender_id', $sender_id);
 
        $stmt->execute();
 
        if ($stmt->rowCount() > 0) {
            $response['error'] = false;
            $id = $this->conn->lastInsertID();
            $stmt = $this->conn->prepare("SELECT teacher.fullname, chat.content, chat.time_sent from chat ".
                    "JOIN teacher ON chat.sender_id = teacher.id AND chat.id = $id");
            $result = $stmt->execute();
            $message = $result->fetch(PDO::FETCH_ASSOC);
            $response['message'] = $message; 
        } else {
            $response['error'] = true;
            $response['message'] = "Failed send advice";
        }
 
        return $response;
    }
 
 
    // fetching all advice topics
    public function getAllAdviceTopics() {
        $stmt = $this->conn->prepare("SELECT * FROM advice_topics");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $tasks;
    }
 
    // fetching advices belonging to a given topic
    function getAdvices($advice_topic_id) {
        $stmt = $this->conn->prepare("SELECT * from advice JOIN teacher ON " .
                "advice.giver_id = teacher.id AND advice.topic_id = $advice_topic_id");
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $tasks;
    }
 
    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($username, $password) {
        $stmt = $this->conn->query("SELECT COUNT(*) from credentials where"
                . " username = '$username' AND password = '$password'");
            // Check for successful select
            if ($stmt->fetchColumn() > 0) {
                
                return TRUE;
            } else {
                return FALSE;
            }
    }
 
    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT user_id, name, email, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($user_id, $name, $email, $created_at);
            $stmt->fetch();
            $user = array();
            $user["user_id"] = $user_id;
            $user["name"] = $name;
            $user["email"] = $email;
            $user["created_at"] = $created_at;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
 
}

