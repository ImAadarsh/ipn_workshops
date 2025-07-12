<?php include "include/header.php"; ?>

<?php include "include/sidebar.php"; ?>
<?php
$uid = $_SESSION['userid'];
$sql = "SELECT * FROM users WHERE id = $uid";
$results = $connect->query($sql);
$final = $results->fetch_assoc();

if (empty($final['designation'])) {
    echo "<script>
        alert('Please update your profile before downloading the certificate.');
        window.location.href = 'profile.php';
    </script>";
}
?>
<div class="content-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="page-title-content">
                    <?php
                    $uid = $_SESSION['userid'];
                    $sql = "SELECT * FROM users where id=$uid";
                    $results = $connect->query($sql);
                    $final = $results->fetch_assoc();
                    $meeting_id = 82945848831;
                    $meeting_passcode = 308453;
                    ?>
                    <p>
                        Welcome Back,
                        <strong class="text-primary"> <?php echo $final['name']; ?> !</strong>
                    </p>
                </div>
            </div>
        </div>
        <!-- TLC 2025 Registration Card -->
        <div class="col-xxl-12 col-xl-12 col-lg-6 mt-4">
            <div class="card" style="border: 1.5px solid #4e73df; border-radius: 18px; box-shadow: 0 4px 18px rgba(78,115,223,0.08);">
                <div class="card-body text-center">
                    <img src="https://ipnacademy.in/conclave/images/black.png" alt="TLC 2025" style="max-width: 180px; margin-bottom: 18px; border-radius: 12px; box-shadow: 0 2px 8px rgba(44,62,80,0.10);">
                    <h4 class="mb-2">Registration for TLC 2025</h4>
                    <p class="mb-3">Be a part of Teachers' Conclave 2025 and join educators across the country in reimagining the 21st Century Classroom with AI.</p>
                    <?php if (isset($final['tlc_2025']) && $final['tlc_2025'] == 1): ?>
                        <div class="d-flex flex-column align-items-center">
                            <a href="https://meet.ipnacademy.in/?display_name=<?php echo $uid; ?>_<?php echo ($final['name']); ?>&mn=<?php echo $meeting_id; ?>&pwd=<?php echo $meeting_passcode; ?>&meeting_email=<?php echo urlencode($final['email']); ?>"
                                class="btn btn-success btn-lg"
                                style="border-radius: 2rem; font-size: 1.05rem; font-weight: 600; background: linear-gradient(90deg, #28a745 0%, #5ce1e6 100%); box-shadow: 0 4px 16px rgba(40,167,69,0.15); padding: 0.8rem 1.2rem; min-width: 0; white-space: normal;"
                                target="_blank">
                                <i class="fa fa-video-camera me-2"></i>
                                Join TLC 2025 Event | Day 2 | Click Now
                            </a>
                            <div id="event-timer" style="margin: 0 auto 18px auto; max-width: 340px; background: rgba(92,225,230,0.08); border-radius: 12px; padding: 16px 0 10px 0; font-size: 1.25rem; font-weight: 700; color: #3a8dde; letter-spacing: 0.02em; box-shadow: 0 2px 8px #5CE1E633; text-align: center;">
                                <span style="font-size:1.1rem; color:#23272f; font-weight:600; display:block; margin-bottom:2px;">Event Starts In</span>
                                <span id="timer-countdown">Loading...</span>
                            </div>

                        </div>
                    <?php else: ?>
                        <button id="tlc2025RegisterBtn" class="btn btn-primary btn-lg" style="padding: 0.6rem 2.2rem; font-size: 1.1rem; border-radius: 2rem; background: linear-gradient(90deg, #2d3e50 0%, #4e73df 100%); border: none; box-shadow: 0 4px 16px rgba(78,115,223,0.15);">Register for TLC 2025</button>
                        <div id="tlc2025RegisterMsg" class="mt-3" style="display:none;"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var btn = document.getElementById('tlc2025RegisterBtn');
                                if (btn) {
                                    btn.addEventListener('click', function() {
                                        btn.disabled = true;
                                        btn.innerText = 'Registering...';
                                        fetch('register_tlc2025.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/x-www-form-urlencoded'
                                                },
                                                body: 'user_id=<?php echo $uid; ?>'
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    btn.classList.remove('btn-primary');
                                                    btn.classList.add('btn-success');
                                                    btn.innerText = 'Already Registered for Event';
                                                    document.getElementById('tlc2025RegisterMsg').style.display = 'block';
                                                    document.getElementById('tlc2025RegisterMsg').innerHTML = '<span class="text-success">You have successfully registered for TLC 2025!</span>';
                                                } else {
                                                    btn.disabled = false;
                                                    btn.innerText = 'Register for TLC 2025';
                                                    document.getElementById('tlc2025RegisterMsg').style.display = 'block';
                                                    document.getElementById('tlc2025RegisterMsg').innerHTML = '<span class="text-danger">' + data.message + '</span>';
                                                }
                                            })
                                            .catch(() => {
                                                btn.disabled = false;
                                                btn.innerText = 'Register for TLC 2025';
                                                document.getElementById('tlc2025RegisterMsg').style.display = 'block';
                                                document.getElementById('tlc2025RegisterMsg').innerHTML = '<span class="text-danger">An error occurred. Please try again.</span>';
                                            });
                                    });
                                }
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- TLC 2025 Certificate Card -->
        <?php if (isset($final['tlc_2025']) && $final['tlc_2025'] == 1): ?>
        <div class="col-xxl-12 col-xl-12 col-lg-6 mt-4">
            <div class="card" style="border: 1.5px solid #28a745; border-radius: 18px; box-shadow: 0 4px 18px rgba(40,167,69,0.08);">
                <div class="card-body text-center">
                    <img src="https://ipnacademy.in/conclave/images/certificate-icon.png" alt="TLC 2025 Certificate" style="max-width: 120px; margin-bottom: 18px; border-radius: 12px; box-shadow: 0 2px 8px rgba(40,167,69,0.15);" onerror="this.style.display='none'">
                    <h4 class="mb-2">TLC 2025 Certificate</h4>
                    <p class="mb-3">Download your participation certificate for Teachers' Leadership Conclave 2025.</p>
                    <a href="tlc_2025.php" id="certificate-download-btn" class="btn btn-success btn-lg" style="border-radius: 2rem; font-size: 1.05rem; font-weight: 600; background: linear-gradient(90deg, #28a745 0%, #ffd700 100%); box-shadow: 0 4px 16px rgba(40,167,69,0.15); padding: 0.8rem 1.2rem; min-width: 0; white-space: normal; display: none;">
                        <i class="fa fa-download me-2"></i>
                        Click Here to Download
                    </a>
                    <div id="certificate-timer" style="margin: 18px auto 0 auto; max-width: 340px; background: rgba(255,215,0,0.08); border-radius: 12px; padding: 16px 0 10px 0; font-size: 1.25rem; font-weight: 700; color: #28a745; letter-spacing: 0.02em; box-shadow: 0 2px 8px rgba(255,215,0,0.2); text-align: center;">
                        <span style="font-size:1.1rem; color:#23272f; font-weight:600; display:block; margin-bottom:2px;">Available in</span>
                        <span id="certificate-countdown">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-xl-12">
                <div class="row">
                    <div class="col-xxl-12 col-xl-12 col-lg-6">
                        <div class="card welcome-profile">
                            <div class="card-body">
                                <img src="<?php echo $uri . $final['profile']; ?>" alt="" />
                                <h4>Hi, <?php echo $final['name']; ?> !</h4>
                                <p><?php echo $final['designation']; ?> </p>
                                <p>
                                    Your Account is verified with the IPN Academy.
                                </p>
                                <ul>
                                    <li>
                                        <a href="#">
                                            <span class="verified"><i class="icofont-check"></i></span>
                                            Institute Name : <?php echo $final['institute_name']; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#">
                                            <span class="verified"><i class="icofont-check"></i></span>
                                            City : <?php echo $final['city']; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#">
                                            <span class="verified"><i class="icofont-check"></i></span>
                                            Email : <?php echo $final['email']; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#">
                                            <span class="verified"><i class="icofont-check"></i></span>
                                            Phone : <?php echo $final['mobile']; ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#">
                                            <?php
                                            $sql1 = "SELECT SUM(p.cpd) AS total_cpd
                                            FROM workshops w
                                            JOIN payments p ON w.id = p.workshop_id
                                            WHERE p.user_id = $uid
                                              AND p.payment_status = 1
                                              AND w.type = 1";
                                            //   echo $sql1;
                                            $result1 = mysqli_query($connect, $sql1);
                                            $row = mysqli_fetch_assoc($result1);
                                            ?>
                                            <span class="verified"><i class="icofont-check"></i></span>
                                            Total CPD Hours Completed : <?php echo $row['total_cpd']; ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <?php include "include/ads.php"; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
    // Countdown Timer to 11 July 2025, 12:30 PM IST
    function updateCountdown() {
        // Target: 11 July 2025, 12:30 PM IST (convert to UTC for JS Date)
        // IST is UTC+5:30
        var eventDate = new Date(Date.UTC(2025, 6, 12, 7, 0, 0)); // July is month 6 (0-based), 7:00 UTC = 12:30 IST
        var now = new Date();
        var diff = eventDate - now;
        if (diff <= 0) {
            document.getElementById('timer-countdown').innerHTML = 'The event has started!';
            return;
        }
        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        var minutes = Math.floor((diff / (1000 * 60)) % 60);
        var seconds = Math.floor((diff / 1000) % 60);
        var parts = [];
        if (days > 0) parts.push(days + 'd');
        if (days > 0 || hours > 0) parts.push(hours + 'h');
        if (days > 0 || hours > 0 || minutes > 0) parts.push(minutes + 'm');
        parts.push(seconds + 's');
        document.getElementById('timer-countdown').innerHTML = parts.join(' : ');
    }
    if (document.getElementById('timer-countdown')) {
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
   
   // Certificate Countdown Timer to 12 July 2025, 7:00 PM IST
   function updateCertificateCountdown() {
       // Target: 12 July 2025, 7:00 PM IST (convert to UTC for JS Date)
       // IST is UTC+5:30, so 7:00 PM IST = 1:30 PM UTC
       var certificateDate = new Date(Date.UTC(2025, 6, 12, 13, 30, 0)); // July is month 6 (0-based), 13:30 UTC = 7:00 PM IST
       var now = new Date();
       var diff = certificateDate - now;
       if (diff <= 0) {
           document.getElementById('certificate-countdown').innerHTML = 'Certificate is now available!';
           document.getElementById('certificate-download-btn').style.display = 'inline-block';
           return;
       }
       var days = Math.floor(diff / (1000 * 60 * 60 * 24));
       var hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
       var minutes = Math.floor((diff / (1000 * 60)) % 60);
       var seconds = Math.floor((diff / 1000) % 60);
       var parts = [];
       if (days > 0) parts.push(days + 'd');
       if (days > 0 || hours > 0) parts.push(hours + 'h');
       if (days > 0 || hours > 0 || minutes > 0) parts.push(minutes + 'm');
       parts.push(seconds + 's');
       document.getElementById('certificate-countdown').innerHTML = parts.join(' : ');
   }
   if (document.getElementById('certificate-countdown')) {
       updateCertificateCountdown();
       setInterval(updateCertificateCountdown, 1000);
   }
</script>
<script src="./vendor/jquery/jquery.min.js"></script>
<script src="./vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./vendor/apexchart/apexcharts.min.js"></script>
<script src="./js/plugins/apex-price.js"></script>
<script src="./vendor/basic-table/jquery.basictable.min.js"></script>
<script src="./js/plugins/basic-table-init.js"></script>
<script src="./vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="./js/plugins/perfect-scrollbar-init.js"></script>
<script src="./js/dashboard.js"></script>
<script src="./js/scripts.js"></script>
</body>

</html>