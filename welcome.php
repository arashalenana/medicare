<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/paths.php';
require_once 'config/rbac.php';

// This landing page is for Admin only — everyone else goes straight to their page
$role = $_SESSION['role'] ?? 'Guest';
if ($role !== 'Admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Welcome - Family Medicare Clinic &amp; Laboratory</title>

    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $asset_path; ?>images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $asset_path; ?>images/favicon-16x16.png">
    <link rel="shortcut icon" href="<?php echo $asset_path; ?>images/favicon.png">

    <link href="<?php echo $asset_path; ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $asset_path; ?>vendor/fontawesome/css/all.min.css">

    <style>
        :root {
            --accent-red: #ED1C24;
            --accent-red-hover: #C1121C;
            --accent-blue: #00AEDB;
            --accent-blue-hover: #008BB0;
            --logo-black: #000000;
            --main-bg: #f8f9fa;
            --text-dark: #333333;
            --text-light: #ffffff;
            --border-color: #e0e0e0;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--main-bg);
            color: var(--text-dark);
        }

        .welcome-screen {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .welcome-topbar {
            background: var(--logo-black);
            color: var(--text-light);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .welcome-topbar .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .welcome-topbar .brand img {
            height: 32px;
            width: 32px;
            border-radius: 6px;
        }

        .welcome-topbar .brand span.accent {
            color: var(--accent-red);
        }

        .welcome-topbar .user-chip {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.85);
        }

        .welcome-main {
            flex: 1;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #ffffff 0%, #f1fbfd 100%);
            overflow: hidden;
        }

        .hero-text h6.eyebrow {
            color: var(--accent-blue);
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.78rem;
            margin-bottom: 10px;
        }

        .hero-text h1 {
            font-weight: 800;
            font-size: 2.1rem;
            color: var(--logo-black);
            line-height: 1.25;
            margin-bottom: 14px;
        }

        .hero-text h1 .accent {
            color: var(--accent-red);
        }

        .hero-text p.lead {
            color: #555;
            font-size: 0.98rem;
            margin-bottom: 22px;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .feature-pill i {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            background: rgba(0, 174, 219, 0.12);
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-enter {
            background-color: var(--accent-red);
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            transition: 0.2s;
        }

        .btn-enter:hover {
            background-color: var(--accent-red-hover);
            color: #fff;
        }

        .hero-illustration {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hero-illustration img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .hero-illustration svg {
            max-width: 100%;
            height: auto;
        }

        @media (max-width: 991.98px) {
            .hero-illustration {
                margin-top:0;
            }
            .hero-illustration img {
                max-width: 80%;
            }
            .hero-text h1 {
                font-size: 1.8rem;
            }   

        @media (max-width: 575.98px) {
            .hero-text h1 {
                font-size: 1.5rem;
            }
            .hero-text p.lead {
                font-size: 0.9rem;
            }
            .hero-illustration img {
                max-width: 100%;
            }
        }
        @media (max-width: 400px) {
            .hero-text h1 {
                font-size: 0.85rem;
            }
            .hero-text p.lead {
                font-size: 0.85rem;
            }
            .hero-illustration img {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-screen">
        <div class="welcome-topbar">
            <div class="brand">
                <img src="<?php echo $asset_path; ?>images/favicon.png" alt="Family Medicare Logo">
                <span>FAMILY MEDICARE <span class="accent">CLINIC &amp; LABORATORY</span></span>
            </div>
            <div class="user-chip">
                Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong> (<?php echo htmlspecialchars($role); ?>)
            </div>
        </div>

        <div class="welcome-main">
            <div class="container py-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-6 hero-text">
                        <h6 class="eyebrow" style="color: #00AEDB; font-size: 1.25rem;">Trusted Family Healthcare</h6>
                        <h1 style="font-size: 1.5rem;">Caring for your family, <span class="accent">one visit at a time.</span></h1>
                        <p class="lead">
                            Family Medicare Clinic &amp; Laboratory provides compassionate consultations, accurate
                            diagnostic testing, and reliable pharmacy services for patients of all ages, backed by a
                            dedicated team and modern clinical management.
                        </p>

                        <div class="feature-pill"><i class="fas fa-user-md"></i> Experienced doctors &amp; clinicians</div>
                        <div class="feature-pill"><i class="fas fa-flask"></i> On-site laboratory &amp; diagnostics</div>
                        <div class="feature-pill"><i class="fas fa-pills"></i> Fully stocked in-house pharmacy</div>

                        <div class="mt-4">
                            <a href="index.php" class="btn btn-enter">
                                Continue to Dashboard <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-6 hero-illustration">
                        <img src="<?php echo $asset_path; ?>images/hero-illustration.jpg" alt="Illustration of a doctor caring for a child" class="img-fluid">
                       
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>