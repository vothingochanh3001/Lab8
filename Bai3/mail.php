<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require "sendemail/phpmailer/src/Exception.php";
require "sendemail/phpmailer/src/PHPMailer.php";
require "sendemail/phpmailer/src/SMTP.php";
if (isset($_POST)) {
    $mail = new PHPMailer(true); 
    try {
        $mail->isSMTP();
        $mail->CharSet  = "utf-8";
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true; 
        $nguoigui = 'hoangviet852004@gmail.com';
        $matkhau = 'nakqppwzfqlxvcsc';
        $tennguoigui = 'VietHoang';
        $mail->Username = $nguoigui; 
        $mail->Password = $matkhau;   
        $mail->SMTPSecure = 'ssl';  
        $mail->Port = 465;                 
        $mail->setFrom($nguoigui, $tennguoigui);
        $to = $_POST['email'];
        $to_name = "VietHoang";
		$tieude = $_POST['tieude'];

        $mail->addAddress($to, $to_name);  
		$mail->addAddress("hoangviet852004@gmail.com","VietHoang");
        $mail->isHTML(true);  
        $mail->Subject = $tieude;
		$noidungthu = ' <div class="card" style="width: 18rem;">
                <div class="card-body">
                    <h5 class="card-title"><b>Xin chào ' . $to_name . '</b></h5>
                    <h6 class="card-subtitle mb-2 text-muted"></h6>
                    <p class="card-text">' . $_POST['content'] . '</p>
                </div>
                </div> ';
        $mail->Body =  $noidungthu;	
			if(isset($_FILES['file']["name"])) {
                $uploadfile = tempnam(sys_get_temp_dir(), sha1($_FILES['file']['name']));
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile))
                    $mail->addAttachment($uploadfile, $_FILES['file']['name']);
            } 
        $mail->smtpConnect(array(
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                    "allow_self_signed" => true
                )
            ));
        if($mail->send())
        echo "
        <script>
        alert('sent successfully');
        document.location.href = 'index.php';   
        </script>
        ";
    } catch (Exception $e) {
        echo 'Mail không gửi được. Lỗi: ', $mail->ErrorInfo;
    }	
    $recipients = "test1@test.com,test2@test.com,test3@test.com,test1@test.com";
    $email_array = explode(",",$recipients);
    foreach($email_array as $email)
    {
       $to      =  $email;
       $mail->addAddress($to, $to_name); 
    }

}
?>