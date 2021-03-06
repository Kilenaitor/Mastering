<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

include("database.php");

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function codeAction(Request $request)
    {
		 $logger = $this->get('logger');
		
         $session = $request->getSession();
         
         if($_POST && !empty($_POST['username']) && !empty($_POST['password']))
         {
             $response = self::validate($_POST['username'], $_POST['password'], $session);	
             //Validate the user when they click submit on the login

             if(isset($response) && $response)
             {
                 goto authorized; //http://xkcd.com/292/
                 //This is a totally fine use for it. authorized is on line 43
             }  
             else if(isset($response))
                 return $this->render('default/login.html.twig', array('error' => "Wrong username or password"));
             else
                 return $this->redirect($this->generateUrl('login'));
         }

         if(!($session->get("authorized", false)))
             return $this->render('default/landing.html.twig');
         else 
         {    
             //Update the goto statement comment as necessary to show where this is. 
             authorized:
             
    		 if($session->get('teacher', false))
    			 $teacher = $session->get('id');
    		 else
    			 $teacher = $session->get('teacher_id', 0);
        
             $con = self::connect();
             
             // Array storing the id of all of the categories that we have
             $categories = mysqli_fetch_all(mysqli_query($con, "SELECT * FROM categories;"), MYSQLI_ASSOC);
 
             $id = $session->get('id');
             
             /**
              * Create a temporary progress array to store the progress
              * of the user. 
              * Grab the title of the problem to cross-compare when we sort
              * through them all.
              */
             $done = array();
             $progress = mysqli_fetch_all(mysqli_query($con, "SELECT title FROM progress LEFT JOIN problems ON problem_id = problems.id WHERE user_id=$id;"), MYSQLI_ASSOC);
             foreach($progress as $key => $value)
                 $done[] = $value['title'];
             
             foreach($categories as $category)
             {
                 $titles = array();
                 $ids = array();
	             
                 $category_id = $category['id'];
                 $category_name = ucwords($category['name']);
                 
                 $result = mysqli_query($con, "SELECT id, title, teacher FROM problems WHERE (teacher=0 OR teacher=$teacher) AND category_id='$category_id';");
 
                 if(mysqli_num_rows($result) > 0)
                 {
                     while($row = $result->fetch_assoc()) 
                     {
                         if(in_array($row['title'], $done) && !$session->get('teacher', false))
                             $titles[$row['title']] = true;
                         else
                             $titles[$row['title']] = false;
                         $ids[] = $row['id'];
                     }
                     $problems["$category_name"] = array('titles' => $titles, 'ids' => $ids);
                 }
             }
             
             return $this->render('default/index.html.twig', 
                 array('problems' => $problems, 
                       'email' => $session->get('username'), 
                       'teacher' => $session->get('teacher', false)));
         }
     }
     
     /**
     * @Route("/privacy", name="privacy")
     */
 	public function privacyAction(Request $request)
 	{
 		return $this->render('default/privacy.html.twig');
 	}
    
     /**
      * @Route("/login", name="login")
      */
    public function loginAction(Request $request)
    {
        $session = $request->getSession();
        if($session->get("authorized", false))
            return $this->redirect($this->generateUrl('homepage'));
         
     	if($_POST && !empty($_POST['username']) && !empty($_POST['password']))
     		$response = self::validate($_POST['username'], $_POST['password'], $session);	//Validate the user when they click submit on the login
        
        if(isset($response) && $response)
            return $this->redirect($this->generateUrl('login'));
        else if(isset($response))
            return $this->render('default/login.html.twig', array('error' => "Wrong username or password"));
        else
            return $this->render('default/login.html.twig');
    }
	
    /**
     * @Route("/teacher", name="teacher_login")
     */
	public function login_teacherAction(Request $request)
	{
        $session = $request->getSession();
         
     	if($_POST && !empty($_POST['username']) && !empty($_POST['password']))
     		$response = self::validate_teacher($_POST['username'], $_POST['password'], $session);
        
        if(isset($response) && $response)
            return $this->redirect($this->generateUrl('admin'));
        else if(isset($response))
            return $this->render('default/login_teacher.html.twig', array('error' => "Wrong username or password"));
        else
            return $this->render('default/login_teacher.html.twig');
	}

    /**
     * @Route("/p{problem}", name="problem")
     */
    public function problemAction(Request $request, $problem)
    {
        $session = $request->getSession();
        
        if(!($session->get("authorized", false)))
            return $this->redirect($this->generateUrl('login'));
        
        $con = self::connect();
        
        $stmt = $con->prepare("SELECT * FROM problems WHERE id=?");
        $stmt -> bind_param('s',$problem);
        
        $result = $stmt->execute() or trigger_error(mysqli_error());
        $rs 	= $stmt->get_result();
        $row 	= $rs->fetch_all(MYSQLI_ASSOC);
        $row 	= $row[0];
        
        $title 		= $row['title'];
        $prompt 	= $row['prompt'];
        $method		= $row['method'];
        $timeout	= $row['timeout'];
        $next_pid   = $row['next_problem_id'];
        
        $stmt = $con->prepare("SELECT function, output, hidden FROM tests WHERE problem_id=?;");
        $stmt -> bind_param('s',$problem);
        
        $result = $stmt->execute() or trigger_error(mysqli_error());
        $rs 	= $stmt->get_result();
        $row 	= $rs->fetch_all(MYSQLI_ASSOC);

        $tests = $row;
        
        if($_POST && isset($_POST['code'])) {
            $return = self::evaluate($_POST['code'], $method, $tests, $timeout, $session->get('username', false));
            $other_tests = 0;
            $pass_others = true;
            foreach($tests as $index => $test) {
                if($test['hidden'] === 1) {
                    $other_tests++;
                    if($return['pass'][$index] === "false")
                        $pass_others = false;
                }
            }
        }
        
        if(empty($return['output']))
            $error = false;
        else
            $error = $return['output'];
        
        if(empty($return['pass']))
            $pass = false;
        else
            $pass = $return['pass'];
        
        if(empty($return['input']))
            $inputs = false;
        else {
            $inputs = $return['input'];
        }
		if(empty($return['all']))
			$all = false;
		else
			$all = true;
		$stmt->close();
        
        if(isset($return)) {
			if($all && !$session->get('teacher')) {
				$id = $session->get('id');
				$check = mysqli_query($con, "SELECT * FROM progress WHERE user_id='$id' AND problem_id='$problem';");
				if(mysqli_num_rows($check) == 0) {
					$query = "INSERT INTO progress (`user_id`, `problem_id`) VALUES ('$id', '$problem');";
					$stmt = $con->prepare($query);
					$stmt->execute();
				}
			}
            return $this->render('default/problem.html.twig', 
                    array('title' => $title, 'prompt' => $prompt, 'method' => $method, 'tests' => $tests, 
					'output' => $error, 'code' => $return['code'], 'pass' => $pass, 'num_others' => $other_tests, 'others' => $pass_others,
					'inputs' => $inputs, 'next_problem' => $next_pid, 'email' => $session->get('username'), 'teacher' => $session->get('teacher',false)));
		}
        else
            return $this->render('default/problem.html.twig', 
                            array('title' => $title, 'prompt' => $prompt, 'method' => $method, 'next_problem' => $next_pid,
							'email' => $session->get('username'), 'teacher' => $session->get('teacher',false)));
        
    }
	
    /**
     * @Route("/admin", name="admin")
     */
	public function adminAction(Request $request)
	{
		$session = $request->getSession();
		if(!$session->get('teacher',false))
			return $this->redirect($this->generateUrl('login'));
		
		$students = self::getStudents($session->get('id'));
		$problems = self::getProblems($session->get('id'));
		
		return $this->render('default/admin.html.twig', array('email' => $session->get('username'), 'teacher' => $session->get('teacher', false), 'students' => $students, 'id' => $session->get('id'), 'problems' => $problems));
	}
	
	public function getStudents($id)
	{
		$con = self::connect();
        if(mysqli_connect_errno()) 
		  echo "Failed to connect to MySQL: " . mysqli_connect_error();
		
		$stmt = $con->prepare("SELECT id, email, username FROM users WHERE teacher=?;");
		$stmt->bind_param('i', $id);
		
		$result = $stmt->execute() or trigger_error(mysqli_error());
		$rs = $stmt->get_result();
		
		$students = $rs->fetch_all(MYSQLI_ASSOC);
		return $students;
	}
	
	public function getProblems($id)
	{
		$con = self::connect();
		
        if(mysqli_connect_errno()) 
		  echo "Failed to connect to MySQL: " . mysqli_connect_error();
		
		$stmt = $con->prepare("SELECT title, method FROM problems WHERE teacher=$id;");
		
		$result = $stmt->execute() or trigger_error(mysqli_error());
		$rs = $stmt->get_result();
		
		$problems = $rs->fetch_all(MYSQLI_ASSOC);
		
		return $problems;
	}
	
    /**
     * @Route("/signup", name="signup")
     */
	public function signupAction(Request $request)
	{
		if($_GET && empty($_GET['key']))
			return $this->render('default/signup.html.twig', array('email' => "", 'error' => true, 'message' => "Missing permission key"));
		
		if($_POST && !empty($_POST['username']) && !empty($_POST['password']) && !empty($_GET['key']))
		{
			$con = self::connect();
			
			$username = $_POST['username'];
			$password = $_POST['password'];
			$key = $_GET['key'];
			
	        if(mysqli_connect_errno()) 
			  echo "Failed to connect to MySQL: " . mysqli_connect_error(); //If that fails, display an error (obviously)
			
			$query = "SELECT * FROM users WHERE username=?;";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows > 0)
				return $this->render('default/signup.html.twig', array('email' => $email, 'error' => false, 'message' => "Username has already been taken"));
			$stmt->close();
            
			$query = "SELECT teacher,email FROM permission WHERE `key`=?;";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $key);
			$stmt->execute();
			$rs = $stmt->get_result();
			$row = $rs->fetch_all(MYSQLI_ASSOC);
			$teacher = $row[0]['teacher'];
			$email = $row[0]['email'];
            $password = password_hash($password, PASSWORD_DEFAULT);
			
			$query = "INSERT INTO users (`email`,`username`, `password`, `teacher`) VALUES ('$email', ?, ?, '$teacher');";
			$stmt = $con->prepare($query);
			$stmt->bind_param('ss', strtolower($username), $password);
			$rs = $stmt->execute();
			$stmt->close();
			$stmt = $con->prepare("UPDATE permission SET used=1 WHERE `key`='$key';");
			$stmt->execute();
			return $this->redirect($this->generateUrl('homepage'));
		}
		
		if($_GET && !empty($_GET['key']))
		{
			$key = $_GET['key'];
			$con = self::connect();
		
	        if(mysqli_connect_errno()) 
			  echo "Failed to connect to MySQL: " . mysqli_connect_error(); //If that fails, display an error (obviously)
			
			$query = "SELECT email FROM permission WHERE `key`=? AND used=0;";
			$stmt = $con->prepare($query);
			$stmt -> bind_param('s', $key);
			
			$result = $stmt->execute();
	        $rs = $stmt->get_result();
	        $row = $rs->fetch_all(MYSQLI_ASSOC);
			
			if(empty($row)) {
				$email = "Email";
				$error = true;
				return $this->render('default/signup.html.twig', array('email' => $email, 'error' => true, 'message' => "Invalid permission key."));
			}
			else
				$email = $row[0]['email'];
			
			$stmt->close();
			$con->close();
			
			return $this->render('default/signup.html.twig', array('email' => $email, 'error' => false));
		}
		
		return $this->render('default/signup.html.twig', array('email' => "Email", 'error' => true, 'message' => "There was an error processing your request. Please try again."));
	}
	
    /**
     * @Route("/teacher_signup", name="teacher_signup")
     */
	public function teacher_signupAction(Request $request)
	{	
		if($_GET && empty($_GET['key']))
			return $this->render('default/teacher_signup.html.twig', array('email' => "Email", 'error' => true, 'message' => "Missing permission key"));
		
		if($_POST && !empty($_POST['username']) && !empty($_POST['password']) && !empty($_POST['key']))
		{
			$con = self::connect();
			
			$username = $_POST['username'];
			$password = $_POST['password'];
			$key 	  = $_POST['key'];
			
	        if(mysqli_connect_errno()) 
			  echo "Failed to connect to MySQL: " . mysqli_connect_error(); //If that fails, display an error (obviously)
			
			$query = "SELECT * FROM teachers WHERE username=?;";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows > 0)
				return $this->render('default/teacher_signup.html.twig', array('email' => $email, 'error' => false, 'message' => "Username has already been taken"));
			
			$stmt->close();
			$query = "SELECT email FROM pem WHERE `key`=?;";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $key);
			$stmt->execute();
			$rs = $stmt->get_result();
			$row = $rs->fetch_all(MYSQLI_ASSOC);
			$email = $row[0]['email'];
            $password = password_hash($password, PASSWORD_DEFAULT);
			
			$query = "INSERT INTO teachers (`email`,`username`, `password`) VALUES ('$email', ?, ?);";
			$stmt = $con->prepare($query);
			$stmt->bind_param('ss', $username, $password);
			$rs = $stmt->execute();
			$stmt->close();
			$stmt = $con->prepare("UPDATE pem SET used=1 WHERE `key`='$key';");
			$stmt->execute();
			
			$session = $this->getRequest()->getSession();
			return $this->redirect($this->generateUrl('login_teacher'));
		}
	
		if($_GET && !empty($_GET['key']))
		{
    		$key = $_GET['key'];
    		$con = self::connect();
	
            if(mysqli_connect_errno()) 
    		  echo "Failed to connect to MySQL: " . mysqli_connect_error(); //If that fails, display an error (obviously)
		
    		$query = "SELECT email FROM pem WHERE `key`=? AND used=0;";
    		$stmt = $con->prepare($query);
    		$stmt -> bind_param('s', $key);
		
    		$result = $stmt->execute();
            $rs = $stmt->get_result();
            $row = $rs->fetch_all(MYSQLI_ASSOC);
		
    		if(empty($row)) {
    			$email = "Email";
    			$error = true;
    			return $this->render('default/teacher_signup.html.twig', array('email' => $email, 'key' => $key, 'error' => true, 'message' => "Invalid permission key."));
    		}
    		else
    			$email = $row[0]['email'];
		
    		$stmt->close();
    		$con->close();
		
    		return $this->render('default/teacher_signup.html.twig', array('email' => $email, 'key' => $key, 'error' => false));
        }
        
        return $this->render('default/teacher_signup.html.twig', array('email' => "Email", 'error' => true, 'message' => "There was an error processing your request. Please try again."));
	}
    
    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction(Request $request) 
    {
        $session = $request->getSession();
        $session->clear();
        return $this->redirect($this->generateUrl('homepage'));
    }
    
    /**
     * @Route("/forgot", name="forgot")
     */ 
    public function forgotAction(Request $request) 
    {
        if($_POST && !empty($_GET['key']) && !empty($_POST['new_password']) && !empty($_POST['new_password_conf'])) {
            $password = $_POST['new_password'];
            $password_conf = $_POST['new_password_conf'];
            
            if($password !== $password_conf) {
                return $this->render('default/forgot_password.html.twig', array('key' => true, 'success' => false, 'error' => "Passwords do not match"));
            }
            
            
            
        }
        if($_POST && !empty($_POST['email'])) {
            
            $con = self::connect();
            
            if(mysqli_connect_errno()) {
    		  echo "Failed to connect to MySQL: " . mysqli_connect_error(); 
              //If that fails, display an error (obviously)
    		}
            
            $email = mysql_real_escape_string($_POST['email']);
            
			$string = random_bytes(32);
			$key = bin2hex($string);
            
            $query = "SELECT id FROM users WHERE email=?;";
            $stmt = $con->prepare($query);
            $stmt->bind_param('s', $email);
            $stmt->execute() or trigger_error(mysqli_error()." ".$query);
            $result = $stmt->get_result();
            
            if($result->num_rows !== 1) {
                return $this->render('default/forgot_password.html.twig', array('success' => false, 'key' => false, 'not_found' => true));
            }
            
            $query = "INSERT INTO password_reset (`email`, `key`) VALUES (?, '$key');";
            $stmt = $con->prepare($query);
            $stmt->bind_param('s', $email);
            
            $stmt->execute() or trigger_error(mysqli_error()." ".$query);
            
            $rows_affected = mysqli_affected_rows($con);
            
            if($rows_affected > 0) {
                
				$link = "http://kilenaitor.science/forgot"."?key=".$key;
			
				$message = \Swift_Message::newInstance()
					->setSubject('MasteringC++: Reset your password')
					->setFrom('codingbatcpp@gmail.com')
					->setTo($email)
					->setBody(
						$this->renderView(
						'Emails/forgot_password.html.twig',
						array('link' => $link)
						),
						'text/html'
				);
			
				$this->get('mailer')->send($message);
                
                return $this->render('default/forgot_password.html.twig', array('success' => true, 'key' => false));
            } else {
                return $this->render('default/forgot_password.html.twig', array('success' => false, 'key' => false));
            }
        }
        if($_GET && !empty($_GET['key'])) {
            return $this->render('default/forgot_password.html.twig', array('key' => true));
        }
        return $this->render('default/forgot_password.html.twig');
    }
    
    private function validate($username, $password, $session) 
	{
        $con = self::connect();
		
        if(mysqli_connect_errno()) {
		  echo "Failed to connect to MySQL: " . mysqli_connect_error(); 
          //If that fails, display an error (obviously)
		}
        
        $username = strtolower($username);
        
        $stmt = $con->prepare("SELECT * FROM users WHERE username=?");
        $stmt -> bind_param('s', $username);
        
        $result = $stmt->execute() or trigger_error(mysqli_error()." ".$query);
        $rs = $stmt->get_result();
        $row = $rs->fetch_all(MYSQLI_ASSOC);
        
        if(!isset($row[0])) {
            mysqli_close($con);
            return false;
        }
        
        $hash = $row[0]['password'];
        
        //Check to see if their entered password 
        //matches the one from their table entry
		if(password_verify($password, $hash))  {
			mysqli_close($con);
            $session->set("authorized", true);
			$session->set("username", $username);
			$session->set("id", $row[0]['id']);
			$session->set("teacher_id", $row[0]['teacher']);
            return true;
		} else {
			mysqli_close($con);
			return false;
		}
    }
	
    private function validate_teacher($username, $password, $session) 
	{
        $con = self::connect();
		
        if(mysqli_connect_errno()) 
		{
		  echo "Failed to connect to MySQL: " . mysqli_connect_error(); //If that fails, display an error (obviously)
		}
        
        $stmt = $con->prepare("SELECT * FROM teachers WHERE username=?");
        $stmt -> bind_param('s',$username);
        
        $result = $stmt->execute() or trigger_error(mysqli_error()." ".$query);
        $rs = $stmt->get_result();
        $row = $rs->fetch_all(MYSQLI_ASSOC);
        if(!isset($row[0]))
        {
            mysqli_close($con);
            return false;
        }
        $hash = $row[0]['password'];
        
		if(password_verify($password, $hash)) //Check to see if their entered password matches the one from their table entry
		{
			mysqli_close($con);
            $session->set("authorized", true);
			$session->set("username", $username);
			$session->set("id", $row[0]['id']);
			$session->set("teacher", true);
            return true;
		}
		else
		{
			mysqli_close($con);
			return false;
		}
    }
    
    private function evaluate($code, $method, $test, $timeout, $username)
    {
        $pass = false;
        $values = "";
        $file_name = $username.".cpp";
        chdir("/opt/files");
        error_log(getcwd());
        $myfile = fopen($file_name, 'w');
        $header = "#include <iostream>\n#include <cmath>\n#include <vector>\n#include <initializer_list>\nusing namespace std;\n\n";
        $vector_print = "template<typename T>\nostream& operator<< (ostream& out, const vector<T>& v) {\nout << \"{\";\nsize_t last = v.size() - 1;\nfor(size_t i = 0; i < v.size(); ++i) {\nout << v[i];\nif (i != last) \nout << \",\";\n}\nout << \"}\";\nreturn out;\n}\n\n";
        $dec = $method."\n{\n";
        $end = "}\n";
        $main = "\n\nint main()\n{\n";
        fwrite($myfile, $header);
        fwrite($myfile, $vector_print);
        fwrite($myfile, $dec);
        fwrite($myfile, "\t".str_replace("\r", '', $code)."\n");
        fwrite($myfile, $end);
        fwrite($myfile, $main);
        foreach($test as $val)
            fwrite($myfile, "\tcout<<boolalpha<<".$val["function"]."<<endl;\n");
        fwrite($myfile, $end);
        fclose($myfile);
        
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
           2 => array("pipe", "w")   // stderr is a pipe to write to
        );
        $logger = $this->get('logger');
        $proc = proc_open('g++ -fno-gnu-keywords -Dasm=prohibited -D__asm__=prohibited --std=c++11 '.$file_name.' -o test.out;', $descriptorspec, $pipes);
        $return = "Compiler Error: ".stream_get_contents($pipes[2]);
        if(file_exists('test.out')) {
            proc_close($proc);
            $handle = popen("ulimit -v 30000", "r");
            $process = proc_open('nice -n10 ./test.out', $descriptorspec, $pipes);
            sleep($timeout);
            $status = proc_get_status($process);
            if($status['running']) {
                proc_terminate($process);
                $return = "Error: Process took to long. Check for any infinite loops.";
                $all = false;
            } 
            else {
                $return = stream_get_contents($pipes[2]);
                if(!empty($return)) {
                    $all = false;
                }   
                else {
                    $return = "";
                    $out = stream_get_contents($pipes[1]);
					$logger->info($out);
                    $out = preg_replace('/[\r\n]+/', '~', $out);
					$logger->info($out);
                    $output_string = "";
                    foreach($test as $val)
                        $output_string .= $val["output"] . "~";
					$compare = self::compare(rtrim($out, "~"), $output_string);
                    $pass = $compare['result'];
					$all = $compare['all'];
                    $values = explode('~', $out);
                    $return = "";
                }
                fclose($pipes[1]);
                proc_close($process);
            }
        }
        else {
            proc_close($proc);
            $all = false;
        }
        if(file_exists($file_name))
            unlink($file_name);
        if(file_exists('test.out'))
            unlink('test.out');
        
        return array('output' => $return, 'code' => $code, 'pass' => $pass, 'input' => $values, 'all' => $all);
    }
    
    private function compare($initial, $compare)
    {
		$counter = 0;
        $result = array();
        
        $initial = explode('~', $initial);
        $compare = explode('~', $compare);
        
        for($i = 0; $i < count($initial); $i++) 
		{
			$initial[$i] === $compare[$i] ? $counter++ : '';
            $result[] = (preg_replace('/[\r\n]+/', '', $initial[$i]) === $compare[$i]) ? "true" : "false";
        }
		
		$all = ($counter == sizeof($initial));
        return array('result' => $result, 'all' => $all);
    }
	
    private function connect()
    {
        return mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB); 
        //Connect to database
    }
}
