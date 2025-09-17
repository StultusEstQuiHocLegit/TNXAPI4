<?php
require_once('../config.php');
require_once('header.php');
?>
<div class="container" style="max-width: 500px; margin: auto; margin-top: 50px;">
    <h1 class="text-center">✉️ CONTACT US</h1>
    <div style="opacity: 0.5;" class="text-center">hi@tnxapi.com</div>
    <br><br>


    <form id="emailForm">
        <div class="form-group">
            <label for="email_subject">in short</label>
            <input
                type="text"
                id="email_subject"
                name="email_subject"
                class="form-control"
                placeholder="flux capacitor stopped fluxing"
            >
        </div>

        <div class="form-group">
            <label for="email_body">in loooooooong</label>
            <textarea
                id="email_body"
                name="email_body"
                class="form-control"
                placeholder="I found a glitch in the mainframe and now everything is full of pixels. Because of that the flux capacitator in the API was damaged and stopped fluxing. Now I see a big timer counting towards self-destruction and me being transformed into Samsa bug gone wild, should I click the red or the blue button?"
                rows="15"
                style="resize: vertical;"
            ></textarea>
        </div>

        <div class="form-group text-center" style="margin-top: 20px;">
            <button
                type="button"
                id="mailtoButton"
                class="btn btn-primary"
                title="always at your service   : )"
            >↗️ SEND EMAIL</button>
        </div>
    </form>
</div>

<script>
    const subjectInput = document.getElementById('email_subject');
    const bodyTextarea = document.getElementById('email_body');
    const mailtoButton = document.getElementById('mailtoButton');


    function toggleButtonVisibility() {
        const subject = subjectInput.value.trim();
        const body = bodyTextarea.value.trim();
        if (subject === '' && body === '') {
            mailtoButton.style.display = 'none';
        } else {
            mailtoButton.style.display = 'inline-block'; // or 'block' if you want full width
        }
    }
    
    // Initial check on page load
    toggleButtonVisibility();
    
    // Listen for input changes on both fields to toggle visibility
    subjectInput.addEventListener('input', toggleButtonVisibility);
    bodyTextarea.addEventListener('input', toggleButtonVisibility);
    
    // // Keep your existing mailto link update code if needed:
    // document.getElementById('emailForm').addEventListener('input', function () {
    //     const subject = encodeURIComponent(subjectInput.value.trim());
    //     const body = encodeURIComponent(bodyTextarea.value.trim());
    //     mailtoLink.href = `mailto:hi@tnxapi.com?subject=${subject}&body=${body}`;
    // });
// 
    // // Update mailto link on input
    // document.getElementById('emailForm').addEventListener('input', function () {
    //     const subject = encodeURIComponent(subjectInput.value.trim());
    //     const body = encodeURIComponent(bodyTextarea.value.trim());
    //     mailtoLink.href = `mailto:hi@tnxapi.com?subject=${subject}&body=${body}`;
    // });

    // // Expand textarea on focus
    // bodyTextarea.addEventListener('focus', () => {
    //     bodyTextarea.rows = 20;
    // });
// 
    // // Collapse textarea on blur (if not clicking inside the textarea)
    // bodyTextarea.addEventListener('blur', () => {
    //     setTimeout(() => {
    //         if (document.activeElement !== bodyTextarea) {
    //             bodyTextarea.rows = 15;
    //         }
    //     }, 100);
    // });

    // Change button on click with confirmation
    mailtoButton.addEventListener('click', (event) => {
        const subject = encodeURIComponent(subjectInput.value.trim());
        // const body = encodeURIComponent(bodyTextarea.value.trim());
        let rawBody = bodyTextarea.value;
        let trimmedBody = rawBody.replace(/^\s+/, ''); // remove all leading whitespace/newlines
        trimmedBody = trimmedBody.replace(/\s+$/, ''); // remove trailing whitespace/newlines
        const body = encodeURIComponent(trimmedBody);
        const mailtoURL = `mailto:hi@tnxapi.com?subject=${subject}&body=${body}`;

        // Check if both fields are empty
        if (subject === '' && trimmedBody === '') {
            alert('Message buffer empty. Please type in something first to prevent intergalactic confusion.');
            return;  // Stop sending
        }

        if (mailtoButton.textContent.includes('AGAIN')) {
            const confirmResend = confirm("This email was already sent. Do you really want to send it again?");
            if (!confirmResend) return;
        }

        // window.location.href = mailtoURL;
        const mailData = parseMailto(mailtoURL);
        sendEmail(mailData);

        mailtoButton.textContent = '✉️ SEND AGAIN';
        mailtoButton.style.opacity = '0.3';
    });
</script>

<?php require_once('FooterEmail.php'); ?>
<?php require_once('footer.php'); ?>
