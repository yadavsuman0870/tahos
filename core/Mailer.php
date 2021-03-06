<?php
namespace core;
require_once($_SERVER['DOCUMENT_ROOT'].'/admin/vendor/autoload.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
/**
 * Класс реализует оповещение на сайте
 */
class Mailer{
	public static $config = [
		'email' => 'sale@tahos.ru',
		'username' => 'sale@tahos.ru',
		'password' => 'Anton12345',
		'host' => 'smtp.mail.ru',
	];
	/**
	 * Отправляет сообщение
	 * @param  [array] $params массив параметров (email, subject, body)
	 * @return [mixed] true в случае удачной отправки либо сообщение об ошибке  
	 */
	public static function send($params){
		$mail = new PHPMailer();
		$mail->isSMTP();
		$mail->isHTML(true);  
		$mail->SMTPDebug = 0;
		$mail->Host = self::$config['host'];
		$mail->SMTPAuth = true;
		$mail->Username = self::$config['username'];
		$mail->Password = self::$config['password'];
		$mail->setFrom(self::$config['email'], 'Tahos.ru');     
		$mail->SMTPSecure = 'tls';            
		$mail->Port = 2525;
		$mail->addAddress($params['email']);
		$mail->addReplyTo(self::$config['email'], 'Tahos.ru');
		$mail->Body = $params['body'];
		$mail->Subject = $params['subject'];
		$mail->CharSet = 'UTF-8';
		if ($mail->send()) return true;
		else return $mail->ErrorInfo;
	}
}
