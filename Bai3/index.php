<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Mail</title>
</head>

<body>
    <form action="mail.php" method="POST">
        <input type="text" name="email" placeholder="Email">
        <input type="text" name="tieude" placeholder="ten">
        <textarea name="content" id="editor" ></textarea>
        <input type="file" name="file">
        <button type="submit" >Gửi</button>
</body>

</html>