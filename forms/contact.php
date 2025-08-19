<?php
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Basic rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_file = sys_get_temp_dir() . '/contact_rate_' . md5($ip);
$current_time = time();
$requests = [];

if (file_exists($rate_file)) {
    $requests = json_decode(file_get_contents($rate_file), true) ?: [];
}

// Remove requests older than 5 minutes
$requests = array_filter($requests, function($time) use ($current_time) {
    return ($current_time - $time) < 300;
});

// Check rate limit (5 requests per 5 minutes)
if (count($requests) >= 5) {
    http_response_code(429);
    die('Too many requests. Please try again later.');
}

// Add current request
$requests[] = $current_time;
file_put_contents($rate_file, json_encode($requests));

// Validate required fields
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['subject']) || empty($_POST['message'])) {
    http_response_code(400);
    die('Please fill in all required fields.');
}

// Sanitize input
$name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars(trim($_POST['subject']), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8');

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die('Please enter a valid email address.');
}

// Both email addresses to receive the message
$receiving_emails = [
    'nabih.ai.agency@gmail.com',
    'mohumedraslan@gmail.com'
];

// Try to load PHP Email Form library
$php_email_form_path = '../assets/vendor/php-email-form/php-email-form.php';
$success_count = 0;

if (file_exists($php_email_form_path)) {
    include($php_email_form_path);
    
    // Send to both email addresses using PHP Email Form
    foreach ($receiving_emails as $receiving_email) {
        try {
            $contact = new PHP_Email_Form;
            $contact->ajax = true;
            
            $contact->to = $receiving_email;
            $contact->from_name = $name;
            $contact->from_email = $email;
            $contact->subject = 'New Contact Form Submission - ' . $subject;

            // Uncomment and configure SMTP if needed for better deliverability
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
            $contact->add_message($subject, 'Subject');
            $contact->add_message($message, 'Message', 10);

            if ($contact->send() === 'OK') {
                $success_count++;
            }
        } catch (Exception $e) {
            error_log("Contact form error for $receiving_email: " . $e->getMessage());
        }
    }
} else {
    // Fallback to native PHP mail() if library not found
    $email_subject = 'New Contact Form Submission - ' . $subject;
    $email_body = "New contact form submission from your website:\n\n";
    $email_body .= "Name: $name\n";
    $email_body .= "Email: $email\n";
    $email_body .= "Subject: $subject\n\n";
    $email_body .= "Message:\n$message\n\n";
    $email_body .= "---\n";
    $email_body .= "Sent from: " . $_SERVER['HTTP_HOST'] . "\n";
    $email_body .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $email_body .= "Time: " . date('Y-m-d H:i:s') . "\n";
    
    $headers = "From: $name <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    foreach ($receiving_emails as $receiving_email) {
        if (mail($receiving_email, $email_subject, $email_body, $headers)) {
            $success_count++;
        }
    }
}

// Return appropriate response
if ($success_count > 0) {
    echo 'OK';
} else {
    http_response_code(500);
    die('Unable to send your message. Please try again later.');
}
?>