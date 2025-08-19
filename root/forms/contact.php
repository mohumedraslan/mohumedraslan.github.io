<?php
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Method Not Allowed');
}

// Validate required fields
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['subject']) || empty($_POST['message'])) {
    http_response_code(400);
    die('Missing required fields');
}

// Sanitize input data
$name = htmlspecialchars(trim($_POST['name']));
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars(trim($_POST['subject']));
$message = htmlspecialchars(trim($_POST['message']));
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die('Invalid email address');
}

// Email addresses to send to
$receiving_emails = [
    'nabih.ai.agency@gmail.com',
    'mohumedraslan@gmail.com'
];

// Check if PHP Email Form library exists
$php_email_form = '../assets/vendor/php-email-form/php-email-form.php';
if (file_exists($php_email_form)) {
    include($php_email_form);
    
    // Send email using the library to both addresses
    $success_count = 0;
    foreach ($receiving_emails as $receiving_email) {
        try {
            $contact = new PHP_Email_Form;
            $contact->ajax = true;
            
            $contact->to = $receiving_email;
            $contact->from_name = $name;
            $contact->from_email = $email;
            $contact->subject = 'New Contact Form Submission from Nabih AI Website - ' . $subject;

            // Add SMTP configuration if needed
            /*
            $contact->smtp = array(
                'host' => 'smtp.gmail.com',
                'username' => 'your-email@gmail.com',
                'password' => 'your-app-password',
                'port' => '587'
            );
            */

            $contact->add_message($name, 'From');
            $contact->add_message($email, 'Email');
            if (!empty($phone)) {
                $contact->add_message($phone, 'Phone');
            }
            $contact->add_message($message, 'Message', 10);

            $result = $contact->send();
            if ($result === 'OK') {
                $success_count++;
            }
        } catch (Exception $e) {
            error_log("Email sending failed for $receiving_email: " . $e->getMessage());
        }
    }
    
    if ($success_count > 0) {
        echo 'OK';
    } else {
        http_response_code(500);
        echo 'Failed to send email';
    }
    
} else {
    // Fallback to native PHP mail() function
    $email_subject = 'New Contact Form Submission from Nabih AI Website - ' . $subject;
    $email_body = "New contact form submission:\n\n";
    $email_body .= "Name: $name\n";
    $email_body .= "Email: $email\n";
    if (!empty($phone)) {
        $email_body .= "Phone: $phone\n";
    }
    $email_body .= "Subject: $subject\n\n";
    $email_body .= "Message:\n$message\n";
    
    $headers = "From: $name <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $success_count = 0;
    foreach ($receiving_emails as $receiving_email) {
        if (mail($receiving_email, $email_subject, $email_body, $headers)) {
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        echo 'OK';
    } else {
        http_response_code(500);
        echo 'Failed to send email';
    }
}
?>