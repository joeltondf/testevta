<?php
// app/services/EmailService.php
// VERSÃO FINAL E LIMPA

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/SmtpConfigModel.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mailer;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->mailer = new PHPMailer(true);
        $this->loadSmtpConfiguration();
    }

    private function loadSmtpConfiguration()
    {
        $smtpConfigModel = new SmtpConfigModel($this->pdo);
        $config = $smtpConfigModel->getSmtpConfig();

        if (empty($config) || empty($config['smtp_host'])) {
            return;
        }

        $this->mailer->isSMTP();
        $this->mailer->Host       = $config['smtp_host'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $config['smtp_user'];
        $this->mailer->Password   = $config['smtp_pass'];
        $this->mailer->SMTPSecure = $config['smtp_security'];
        $this->mailer->Port       = (int)$config['smtp_port'];
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->isHTML(true);
        
        if (!empty($config['smtp_from_email'])) {
            $this->mailer->setFrom($config['smtp_from_email'], $config['smtp_from_name']);
        }
    }

    public function sendEmail($to, $subject, $body)
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;
            $this->mailer->AltBody = strip_tags($body);

            return $this->mailer->send();

        } catch (Exception $e) {
            throw new Exception("PHPMailer Error: {$this->mailer->ErrorInfo}");
        }
    }
}