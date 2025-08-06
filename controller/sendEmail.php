<?php
require '../vendor/autoload.php';
require '../model/mail_config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Embed CSS styles
$emailStyles = "
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h2 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
            margin: 10px 0;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            font-size: 16px;
            margin: 10px 0;
        }
        li strong {
            color: #2c3e50;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #777777;
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #7a22de;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #5f1ab3;
        }
    </style>
";

function sendEmail($recipientEmail, $subject, $body, $isHtml = true) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($recipientEmail);

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        if (!$isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        error_log("Email sent to $recipientEmail: $subject");
        return true;
    } catch (Exception $e) {
        error_log("Failed to send email to $recipientEmail: {$mail->ErrorInfo}");
        return false;
    }
}

function sendNewRequestEmail($recipientEmail, $requestId, $submitterName, $leaveType, $startDate, $endDate) {
    global $emailStyles;
    $subject = "New Leave Request Submitted (#$requestId)";
    $body = "
        $emailStyles
        <div class='email-container'>
            <h2>New Leave Request</h2>
            <p>Dear User,</p>
            <p>A new leave request has been submitted:</p>
            <ul>
                <li><strong>Request ID:</strong> #$requestId</li>
                <li><strong>Submitted by:</strong> $submitterName</li>
                <li><strong>Leave Type:</strong> " . ucfirst($leaveType) . "</li>
                <li><strong>Start Date:</strong> $startDate</li>
                <li><strong>End Date:</strong> $endDate</li>
            </ul>
            <p><a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/gestionConge/view/admin-approval.php' class='button'>Review Request</a></p>
            <p class='footer'>Best regards,<br>Gestion Congés Team</p>
        </div>
    ";
    return sendEmail($recipientEmail, $subject, $body);
}

function sendRequestStatusEmail($recipientEmail, $requestId, $status, $reviewerName) {
    global $emailStyles;
    $subject = "Leave Request #$requestId " . ucfirst($status);
    $body = "
        $emailStyles
        <div class='email-container'>
            <h2>Leave Request Update</h2>
            <p>Dear User,</p>
            <p>Your leave request (ID: #$requestId) has been <strong>$status</strong> by $reviewerName.</p>
            <p><a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/gestionConge/view/consult-requests.php' class='button'>View Details</a></p>
            <p class='footer'>Best regards,<br>Gestion Congés Team</p>
        </div>
    ";
    return sendEmail($recipientEmail, $subject, $body);
}

function sendNewEventEmail($recipientEmail, $eventTitle, $startDate, $endDate, $rhName) {
    global $emailStyles;
    $subject = "New Event: $eventTitle";
    $body = "
        $emailStyles
        <div class='email-container'>
            <h2>New Event Notification</h2>
            <p>Dear User,</p>
            <p>A new event has been added to your calendar:</p>
            <ul>
                <li><strong>Title:</strong> $eventTitle</li>
                <li><strong>Start Date:</strong> $startDate</li>
                <li><strong>End Date:</strong> " . ($endDate ?: 'N/A') . "</li>
                <li><strong>Created by:</strong> $rhName</li>
            </ul>
            <p><a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/gestionConge/view/Dashboard.php' class='button'>View Calendar</a></p>
            <p class='footer'>Best regards,<br>Gestion Congés Team</p>
        </div>
    ";
    return sendEmail($recipientEmail, $subject, $body);
}
?>