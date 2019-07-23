<?php

namespace harrybailey\Yii2ExtendPostmark;

use yii;
use yii\base\InvalidConfigException;
use yii\web\ServerErrorHttpException;

class PostmarkMailer extends \yii\base\Component implements \yii\mail\MailerInterface
{
	private 
		$postmarkServerToken,
		$errorEmailAddress,
		$safeEmailAddress,
		$viewPath,
		$environment,
		$production,

		$client,

		$to,
		$from,
		$subject,
		$htmlBody = NULL,
		$plaintextBody = NULL,
		$attachments = [];

	/**
	 * Create the Postmark client. postmarkServerToken, errorEmailAddress, safeEmailAddress, viewPath, environment, and production must have been set in the config of the component
	 *
	 * @return $this
	 */
	public function init()
	{
		parent::init();

		if (!isset($this->postmarkServerToken)) {
			throw new InvalidConfigException('postmarkServerToken must be set');
		}

		if (!isset($this->errorEmailAddress)) {
			throw new InvalidConfigException('errorEmailAddress must be set');
		}

		if (!isset($this->safeEmailAddress)) {
			throw new InvalidConfigException('safeEmailAddress must be set');
		}

		if (!isset($this->viewPath)) {
			throw new InvalidConfigException('viewPath must be set');
		}

		if (!isset($this->environment)) {
			throw new InvalidConfigException('environment must be set');
		}

		if (!isset($this->production)) {
			throw new InvalidConfigException('production must be set');
		}

		$this->client = new \Postmark\PostmarkClient($this->postmarkServerToken);

		return $this;
	}

	/**
	 * Allow $this->postmarkServerToken to be set in the config of the component.
	 *
	 * @param string $value The Postmark token for the server you'd like to use to send/receive email from.
	 */
	public function setPostmarkServerToken($value)
	{
		$this->postmarkServerToken = $value;
	}

	/**
	 * Allow $this->errorEmailAddress to be set in the config of the component.
	 *
	 * @param string $value The email address to send error reports to.
	 */
	public function setErrorEmailAddress($value)
	{
		$this->errorEmailAddress = $value;
	}

	/**
	 * Allow $this->safeEmailAddress to be set in the config of the component.
	 *
	 * @param string $value The email address to send all emails to when not on production.
	 */
	public function setSafeEmailAddress($value)
	{
		$this->safeEmailAddress = $value;
	}

	/**
	 * Allow $this->viewPath to be set in the config of the component.
	 *
	 * @param string $value The filepath to the views used to generate the email body in compose().
	 */
	public function setViewPath($value)
	{
		$this->viewPath = $value;
	}

	/**
	 * Allow $this->environment to be set in the config of the component.
	 *
	 * @param string $value The current environment.
	 */
	public function setEnvironment($value)
	{
		$this->environment = $value;
	}

	/**
	 * Allow $this->production to be set in the config of the component.
	 *
	 * @param string|array $value The value(s) of environment when it is production or the equivalent.
	 */
	public function setProduction($value)
	{
		if (!is_array($value)) {
			$this->production = [$value];

		} else {
			$this->production = $value;
		}
	}

	/**
	 * Set the message body based on view files
	 *
	 * @param array $viewFiles The html and text files to use to generate the email body.
	 * @param array $params The parameters to pass to the views to generate the email body.
	 * @return $this
	 */
	public function compose($view = null, array $params = [])
	{
		if (is_array($view)) {

			if (isset($view['html'])) {
				$htmlViewPath = $this->viewPath.'/'.$view['html'];
			} else {
				$htmlViewPath = $view;
			}

			if (isset($view['text'])) {
				$textViewPath = $this->viewPath.'/'.$view['text'];
			}

		}

		$html = '';
		$plaintext = '';

		if (isset($htmlViewPath)
			&& $htmlViewPath !== '') {
			
			try {
				$html = Yii::$app->controller->renderPartial($htmlViewPath, $params);

			} catch (\Exception $e) {
				$html = '';
			}
		}

		if (isset($textViewPath)
			&& $textViewPath !== '') {
			
			try {
				$plaintext = Yii::$app->controller->renderPartial($textViewPath, $params);

			} catch (\Exception $e) {
				$plaintext = strip_tags($html);
			}

		} else {
			$plaintext = strip_tags($html);
		}

		return $this
			->messageHtml($html)
			->messagePlain($plaintext);
	}

	/**
	 * Set the message subject
	 *
	 * @param string $subject The subject for the message.
	 * @return $this
	 */
	public function subject($subject)
	{
		$this->subject = $subject;

		return $this;
	}

	/**
	 * Set the HTML message body
	 *
	 * @param string $body The HTML body for the message.
	 * @return $this
	 */
	public function messageHtml($body)
	{
		$this->htmlBody = $body;

		return $this;
	}

	/**
	 * Set the plaintext message body
	 *
	 * @param string $body The plaintext body for the message.
	 * @return $this
	 */
	public function messagePlain($body)
	{
		$this->plaintextBody = $body;

		return $this;
	}

	/**
	 * Add a new recipient for the message
	 *
	 * @param string $email The recipient email address.
	 * @param string $name The recipient name.
	 * @return $this
	 */
	public function addTo($email, $name = null)
	{
		$this->to .= $this->processEmail($email, $name);

		return $this;
	}

	/**
	 * Set the recipient for the message (replaces any previously set recipients)
	 *
	 * @param string $email The recipient email address.
	 * @param string $name The recipient name.
	 * @return $this
	 */
	public function to($email, $name = null)
	{
		$this->to = $this->processEmail($email, $name);

		return $this;
	}

	/**
	 * Set the sender of the message
	 *
	 * @param string $email The sender email address.
	 * @param string $name The sender name.
	 * @return $this
	 */
	public function from($email, $name = null)
	{
		$this->from = $this->processEmail($email, $name);

		return $this;
	}

	/**
	 * Add an attachment to the message
	 *
	 * @param string $name The name of the attachment.
	 * @param string $filePath The filepath of the file to attach.
	 * @param string $type The type of the file to attach.
	 * @return $this
	 */
	public function addCustomAttachment($name, $filePath, $type)
	{
		$this->attachments[] = \Postmark\Models\PostmarkAttachment::fromFile($filePath, $name, $type);
		unlink($filePath);

		return $this;
	}

	/**
	 * Send the message
	 *
	 * @return boolean Whether the email successfully sent.
	 */
	public function send($message = null)
	{
		// if $message param used (automatic use)

		if (!is_null($message)) {

			if (is_array($message->getTo())) {
				foreach ($message->getTo() as $to) {
					$this->addTo($to);
				}
			} else {
				$this->to($message->getTo());
			}

			if (is_array($message->getFrom())) {
				// not sure what format this will be in if an array! We can't deal with this!
				throw new ServerErrorHttpException("Unknown from format");
				
			} else {
				$this->from($message->getFrom());
			}

			$this->subject($message->getSubject());
		}


		// Confirm all needed variables are set

		if (is_null($this->to) || empty($this->to)) {
			throw new ServerErrorHttpException("To email cannot be blank");
		}

		if (is_null($this->subject) || empty($this->subject)) {
			throw new ServerErrorHttpException("Subject cannot be blank");
		}

		if ((is_null($this->htmlBody) || empty($this->htmlBody))
			&& (is_null($this->plaintextBody) || empty($this->plaintextBody))) {
			throw new ServerErrorHttpException("Email body cannot be blank");
		}
		
		// Send by mail() if an error email
		if ($this->toIsErrorEmail()) {
			return mail($this->to, $this->subject, $this->plaintextBody, ['From: '.$this->from]);
		}

		// check environment
		if (!in_array($this->environment, $this->production)) {
				
			$this->to($this->safeEmailAddress, 'Safe Email Address');

			$this->subject = '['.$this->environment.'] '.$this->subject;
		}

		$response = $this->client->sendEmail(
			$this->from, 
			$this->to, 
			$this->subject, 
			$this->htmlBody, 
			$this->plaintextBody,
			NULL, // tag
			true, // track opens
			NULL, // reply to
			NULL, // cc
			NULL, // bcc
			NULL, // headers
			$this->attachments, // attachments
			NULL, // track links
			NULL // metadata
		);

		return $response->errorcode === 0;
	}

	public function sendMultiple(array $messages)
	{
		$count = 0;

		foreach ($messages as $message) {

			try {
				if ($this->send($message)) {
					$count++;
				}
			} catch (\Exception $e) {
				// do not count
			}
		}

		return $count;
	}

	private function processEmail($email, $name)
	{
		return is_null($email) ? null : (is_null($name) ? $email : (str_replace(',', '', $name).' <'.$email.'>,'));
	}

	private function toIsErrorEmail()
	{
		$toString = trim($this->to, ',');

		$toArray = explode(',', $toString);

		// there is only one recipient and that recipient contains the errorEmailAddress
		return sizeof($toArray) == 1 && strpos($toString, $this->errorEmailAddress) !== false;
	}
}