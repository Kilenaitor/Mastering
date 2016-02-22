<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

include 'random.php';

class AjaxController extends Controller
{
    /**
    * @Route("/ajax/enroll", name="ajax_enroll")
    */
	public function addStudentAction(Request $request)
	{
		$session = $request->getSession();
		if($session->get('teacher', false))
		{
			$string = random_bytes(16);
			$email = $_POST['email'];
			$key = bin2hex($string);
			$teacher = $_POST['id'];
		
			$con = self::connect();
		
			$query = "SELECT * FROM users WHERE email=?";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $email);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows > 0) {
				return new JsonResponse(array('status' => 400, 'message' => "Student is already enrolled!"));
			}
			$stmt->close();
		
			$query = "SELECT * FROM permission WHERE email=?";
			$stmt = $con->prepare($query);
			$stmt->bind_param('s', $email);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows > 0) {
				return new JsonResponse(array('status' => 400, 'message' => "Student has already been invited."));
			}
			$stmt->close();
		
			$query = "INSERT INTO permission (`email`, `key`, `teacher`) VALUES (?,?,?)";
			$stmt = $con->prepare($query);
			$stmt->bind_param('ssi', $email, $key, $teacher);
		
			$result = $stmt->execute();
		
			$result = $stmt->affected_rows > 0;
		
			if($result) {
			
				$stmt = $con->prepare("SELECT email FROM teachers WHERE id=$teacher");
				$result = $stmt->execute();
				$rs = $stmt->get_result();
				$row = $rs->fetch_all(MYSQLI_ASSOC);
				$teach = $row[0]['email'];
				$link = "http://kilenaitor.science/signup"."?key=".$key;
			
				$message = \Swift_Message::newInstance()
					->setSubject('You have been invited to join CodingBat C++')
					->setFrom('codingbatcpp@gmail.com')
					->setTo($email)
					->setBody(
						$this->renderView(
						'Emails/registration.html.twig',
						array('teacher' => $teach, 'link' => $link)
						),
						'text/html'
				);
			
				$this->get('mailer')->send($message);
			
				return new JsonResponse(array('status' => 200, 'message' => "Email sent!"));
			}
			else
				return new JsonResponse(array('status' => 400, 'message' => "Error processing request. Please try again."));
		}
		else
			throw new HttpException(401, 'Unauthorized access.');
	}
	
    /**
    * @Route("/ajax/progress", name="ajax_progress")
    */
	public function progressAction(Request $request) 
	{
		$session = $request->getSession();
		if($session->get('teacher', false))
		{
			$id = $_GET['id'];
		
			$con = self::connect();
		
			$query = "SELECT title,finished FROM progress INNER JOIN problems ON problem_id = problems.id WHERE user_id=?";
			$stmt = $con->prepare($query);
			$stmt->bind_param('i',$id);
			$result = $stmt->execute();
		
			$rs = $stmt->get_result();
			$progress = $rs->fetch_all(MYSQLI_ASSOC);
		
			return new JSONResponse($progress);
		}
		else
			throw new HttpException(401, 'Unauthorized access.');
	}
	
    /**
    * @Route("/ajax/delete", name="ajax_delete")
    */
	public function deleteAction(Request $request)
	{
		$session = $request->getSession();
		if($session->get('teacher', false))
		{
			$id = $this->getRequest()->get('id');
	
			$con = self::connect();
	
			//Delete will remove all traces of the student from the site.
	
			$query = "DELETE FROM users WHERE id=?";
			$stmt = $con->prepare($query);
			$stmt->bind_param('i',$id);
			$stmt->execute(); 
			if($stmt->affected_rows == 0)
				return new JSONResponse(array('status' => 400, 'message' => "Error processing request. Please try again."));
			$stmt->close();
			$query = "DELETE FROM progress WHERE user_id=?";
			$stmt = $con->prepare($query);
			$stmt->bind_param('i',$id);
			$stmt->execute();
	
			return new JSONResponse(array('status' => 200, 'message' => "Successfully deleted student."));
		}
		else
			throw new HttpException(401, 'Unauthorized access.');
	}
	
    /**
    * @Route("/ajax/teacher", name="ajax_delete")
    */
	public function teacherAction(Request $request) 
	{
		$session = $request->getSession();
		if($session->get('admin', false))
		{
			$generator = new SecureRandom();
			$random = $generator->nextBytes(8);
		
			$email = $_POST['email'];
			$key = bin2hex($random);
			
			$con = self::connect();
			
			$query = "INSERT INTO pem (`key`, `email`) VALUES (?,?)";
			$stmt = $con->prepare($query);
			$stmt->bind_param('ss',$key, $email);
			$stmt->execute();
			
			$result = $stmt->affected_rows > 0;
			
			if($result)
			{	
				$link = "http://localhost:8000/code/teacher_signup"."?key=".$key;
		
				$message = \Swift_Message::newInstance()
					->setSubject('Welcome to CodingBat C++')
					->setFrom('codingbatcpp@gmail.com')
					->setTo($email)
					->setBody(
						$this->renderView(
						'Emails/teacher.html.twig',
						array('key' => $key, 'link' => $link)
						),
						'text/html'
				);
				
				$this->get('mailer')->send($message);
			
				return new JsonResponse(array('status' => 200, 'message' => "Email sent!"));
			}
			else
				return new JsonResponse(array('status' => 400, 'message' => "Error processing request. Please try again."));
				
		}
		else
			throw new HttpException(401, 'Unauthorized access.');
	}
	
    /**
    * @Route("/ajax/problem", name="ajax_problem")
    */
	public function problemAction(Request $request)
	{
		$session = $request->getSession();
		if($session->get('admin', false))
		{
			try 
			{
				$con = self::connect();
                
				$title 		= $_POST['title'];
				$prompt 	= $_POST['prompt'];
				$method 	= $_POST['method'];
				$test 		= $_POST['test'];
				$output 	= $_POST['output'];
				$category	= $_POST['category'];
			
				$query	= "INSERT INTO problems (`title`, `prompt`, `method`, `test`, `output`, `category`) VALUES (?,?,?,?,?,?)";
				$stmt 	= $con->prepare($query);
				$stmt->bind_param('ssssss', $title, $prompt, $method, $test, $output, $category);
				$stmt->execute();
			
				$result	= $stmt->affected_rows > 0;
			
				if($result)
					return new JsonResponse(array('status' => 200, 'message' => "Problem added!"));
				else
					return new JsonResponse(array('status' => 400, 'message' => "Bad input."));
			} 
			catch(Exception $e)
			{
				return new JsonResponse(array('status' => 400, 'message' => $e->getMessage()));
			}
		}
		else if($session->get('teacher', false))
		{
			try 
			{
				$con = self::connect();
				
				$title 		= $_POST['title'];
				$prompt 	= $_POST['prompt'];
				$method 	= $_POST['method'];
				$tests 		= $_POST['test'];
				$outputs 	= $_POST['output'];
                $hiddens    = $_POST['hidden'];
				$category	= 22;
				$teacher	= $session->get('id');
			
				$query = "INSERT INTO problems (`title`, `prompt`, `method`, `category`, `teacher`) VALUES (?,?,?,?,?)";
				$stmt = $con->prepare($query);
				$stmt->bind_param('sssii', $title, $prompt, $method, $category, $teacher);
				$stmt->execute();
                
			    $problem_id = mysqli_insert_id($con);
				$result = $stmt->affected_rows > 0;
                
				if(!$result)
                    return new JsonResponse(array('status' => 400, 'message' => "Bad input."));
                
                foreach($tests as $index => $test) {
    				$query = "INSERT INTO tests (`problem_id`, `function`, `output`, `hidden`) VALUES (?,?,?,?)";
    				$stmt = $con->prepare($query);
    				$stmt->bind_param('issi', $problem_id, $test, $outputs[$index], $hiddens[$index]);
    				$stmt->execute();
                    $result = $stmt->affected_rows > 0;
                    
    				if(!$result)
                        return new JsonResponse(array('status' => 400, 'message' => "Failed to add test cases."));
                }
                
				return new JsonResponse(array('status' => 200, 'message' => "Problem added!"));
			} 
			catch(Exception $e)
			{
				return new JsonResponse(array('status' => 400, 'message' => $e->getMessage()));
			}
		}
		else
			throw new HttpException(401, "Unathorized access.");
	}
	
    private function connect() {
        return mysqli_connect("localhost","KyleM","Minshall1!", "Site"); //Connect to database
    }
}