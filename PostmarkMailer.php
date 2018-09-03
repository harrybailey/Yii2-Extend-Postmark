<?php

namespace Human\Yii2ExtendPostmark;

use yii;
use yii\base\InvalidConfigException;
use yii\web\ServerErrorHttpException;

class PostmarkMailer extends \yii\base\Component
{
	private 
		$postmarkServerToken,
		$errorEmailAddress,
		$safeEmailAddress,
		$viewPath,

		$client,

		$to,
		$from,
		$subject,
		$htmlBody = NULL,
		$plainTextBody = NULL,
		$attachments = [];

	/**
	 * Initialise the Postmark client
	 *
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

		$this->client = new \Postmark\PostmarkClient($this->postmarkServerToken);

		return $this;
	}

	/**
	 * Allow $this->postmarkServerToken to be set
	 *
	 * @param string $value The token associated with "Server" you'd like to use to send/receive email from.
	 */
	public function setPostmarkServerToken($value)
	{
		$this->postmarkServerToken = $value;
	}

	/**
	 * Allow $this->errorEmailAddress to be set
	 *
	 * @param string $value The email address to send error reports to.
	 */
	public function setErrorEmailAddress($value)
	{
		$this->errorEmailAddress = $value;
	}

	/**
	 * Allow $this->safeEmailAddress to be set
	 *
	 * @param string $value The email address to send all emails to when not on production.
	 */
	public function setSafeEmailAddress($value)
	{
		$this->safeEmailAddress = $value;
	}

	/**
	 * Allow $this->viewPath to be set
	 *
	 * @param string $value The filepath to the views used to generate the email body in compose().
	 */
	public function setViewPath($value)
	{
		$this->viewPath = $value;
	}

	/**
	 * Set the message body based on view files
	 *
	 * @param array $viewFiles The html and text files to use to generate the email body.
	 * @param array $params The parameters to pass to the views to generate the email body.
	 * @return $this
	 */
	public function compose($viewFiles, $params)
	{
		return $this
			->messageHtml(Yii::$app->controller->renderPartial($this->viewPath.'/'.$viewFiles['html'], $params))
			->messagePlain(Yii::$app->controller->renderPartial($this->viewPath.'/'.$viewFiles['text'], $params));
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
		$this->plainTextBody = $body;

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
	public function send()
	{
		try {

			if (is_null($this->to) || empty($this->to)) {
				throw new ServerErrorHttpException("To email cannot be blank");
			}

			if (is_null($this->subject) || empty($this->subject)) {
				throw new ServerErrorHttpException("Subject cannot be blank");
			}

			if ((is_null($this->htmlBody) || empty($this->htmlBody))
				&& (is_null($this->plainTextBody) || empty($this->plainTextBody))) {
				throw new ServerErrorHttpException("Email body cannot be blank");
			}

			if (YII_ENV_DEV
				&& !$this->toIsErrorEmail()) {
					
				$this->to($this->safeEmailAddress, 'Safe Email Address');
			}

			try {
				$response = $this->client->sendEmail(
					$this->from, 
					$this->to, 
					$this->subject, 
					$this->htmlBody, 
					$this->plainTextBody,
					NULL, // tag
					true, // track opens
					NULL, // reply to
					NULL, // cc
					NULL, // bcc
					NULL, // headers
					$this->attachments, // attachments
					NULL // track links
				);

			} catch(\Postmark\Models\PostmarkException $e) {
				throw new ServerErrorHttpException("The email failed to send");

			} catch(\Exception $generalException){
				throw new ServerErrorHttpException("The email failed to send");
			}

		} catch(\CHttpException $exception){
			// preferably do something with the error message here
			return false;
		}

		return $response->errorcode === 0;
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