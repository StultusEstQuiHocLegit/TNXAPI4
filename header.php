<html>
<head>
	<meta charset="utf-8"/>
    <title>TNX API</title>
  	<header>
  	<!-- <link rel="stylesheet" type="text/css" href="./style.css"> -->
    <link rel="stylesheet" type="text/css" href="./style.css">
    <link rel="stylesheet" type="text/css" href="./StyleLightmode.css">
  	<link rel="icon" type="image/png" href="./logos/favicon.png">

    <!-- /* include emojis font to have a similar layout on different devices */ -->
    <!-- <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet"> -->

    <!-- PWA (progressive web app) -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#000000">
</header>

<style type="text/css">
@font-face {
    font-family: 'OCR A Std';
    font-style: normal;
    font-weight: normal;
    src: local('OCR A Std'), url('OCRAStd.woff') format('woff');
}

body {
  font-family: 'OCR A Std', monospace;
}

.emoji, .app-icon, .emojiMenuPart {
    /* font-family: 'Noto Color Emoji', sans-serif; */
}
</style><!-- example: <span class="emoji">📖</span> or: <span class='emoji'>📖</span> -->

<body>
<div class="header">
    <a href="./index.php" title="TNX API"><img src="./logos/TramannLogo.png" height="40" alt="TNX API"></a>
</div>
<footer class="footerleft" style="z-index: 500;">
    <div style="opacity: 0.4;"><a href="mailto:hi@tnxapi.com?subject=Hi  : )&body=Hi,%0D%0A%0D%0A%0D%0A[ContentOfYourMessage]%0D%0A%0D%0A%0D%0A%0D%0AWith best regards,%0D%0A[YourName]" title="Always at your service   : )"><span class="emoji">✉️</span> CONTACT US   : )</a></div>
</footer>
<footer class="footerright" style="z-index: 500;">
    <div style="opacity: 0.2;"><a href="./imprint.php"><span class="emoji">🖋️</span> IMPRINT</a> - <a href="./DataSecurity.php"><span class="emoji">🔒</span> DATA SECURITY</a></div>
</footer>
<div id="cookie-container">
    <div id="cookie-content">
        <div id="cookie-sentences">To give you the best user experience on our websites, we use cookies.<br>By continuing to use our websites, you consent to the use of cookies.</div>
        <button id="close-cookie">&times;</button>
    </div>
</div>
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var cookieContainer = document.getElementById("cookie-container");
    var closeButton = document.getElementById("close-cookie");

    // check if cookie alert has already been closed
    if (!getCookie("cookieAccepted")) {
        cookieContainer.style.display = "block";
    }

    // event listener to close the cookie alert
    closeButton.addEventListener("click", function() {
        cookieContainer.style.display = "none";
        // set cookie to save that cookie alert was accepted
        setCookie("cookieAccepted", "true", 3650); // 3650 days = 10 years
    });

    // function to read cookies
    function getCookie(name) {
        var cookieArr = document.cookie.split("; ");
        for (var i = 0; i < cookieArr.length; i++) {
            var cookiePair = cookieArr[i].split("=");
            if (name === cookiePair[0]) {
                return cookiePair[1];
            }
        }
        return null;
    }

    // function to set cookies
    function setCookie(name, value, days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "expires=" + date.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
    }
});
</script>
