<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: public/dashboard.php'); exit; }
require_once __DIR__ . '/config/database.php';

$error = '';
$stats = ['total_burials'=>0,'total_lots'=>0,'total_sections'=>0,'total_blocks'=>0,'total_images'=>0];
try {
    $db = new Database(); $conn = $db->getConnection();
    if ($conn) {
        $stats['total_burials']  = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived=0")->fetchColumn();
        $stats['total_lots']     = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['total_sections'] = $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();
        $stats['total_blocks']   = $conn->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
        try {
            $stats['total_images'] = $conn->query("SELECT COUNT(*) FROM burial_record_images")->fetchColumn();
        } catch (Exception $e) { $stats['total_images'] = 0; }
    }
} catch (Exception $e) {}

$open_modal = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $open_modal = true;
    if ($username && $password) {
        try {
            $db = new Database(); $conn = $db->getConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username=:u AND is_active=1");
                $stmt->execute([':u' => $username]);
                $user = $stmt->fetch();
                if ($user && $user['password_hash'] === $password) {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $conn->prepare("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id'=>$user['id']]);
                    // Log successful login
                    require_once __DIR__ . '/config/logger.php';
                    logActivity($conn, 'LOGIN', 'users', $user['id'], 'User "' . $user['username'] . '" logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    header('Location: public/dashboard.php'); exit;
                } else {
                    $error = 'Invalid username or password.';
                    // Log failed login attempt
                    try {
                        require_once __DIR__ . '/config/logger.php';
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $stmt2 = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, session_id) VALUES (NULL, 'FAILED_LOGIN', 'users', NULL, :desc, :ip, :sid)");
                        $stmt2->execute([':desc' => 'Failed login attempt for username "' . htmlspecialchars($username) . '" from ' . $ip, ':ip' => $ip, ':sid' => session_id()]);
                    } catch (Exception $e) { /* silent */ }
                }
            } else { $error = 'Database connection failed.'; }
        } catch (Exception $e) { $error = 'Error: '.$e->getMessage(); }
    } else { $error = 'Please enter both username and password.'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Holy Spirit Parish Cemetery — Barcenaga, Naujan, Oriental Mindoro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── RESET & BASE ── */
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Inter',system-ui,sans-serif;color:#1a1a2e;background:#fff;overflow-x:hidden}

/* ══════════════════════════════════════
   NAVBAR  — modernized
══════════════════════════════════════ */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:1000;
  transition:background .35s,box-shadow .35s,padding .35s;
  padding:0;
}
.nav.scrolled{
  background:rgba(8,12,28,.96);
  backdrop-filter:blur(18px);
  box-shadow:0 4px 32px rgba(0,0,0,.45);
}
.nav-inner{
  max-width:1320px;margin:0 auto;padding:0 2rem;
  height:76px;display:flex;align-items:center;justify-content:space-between;
}
/* brand */
.nav-brand{display:flex;align-items:center;gap:.9rem;text-decoration:none}
.brand-icon{
  width:42px;height:42px;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.18);
  border-radius:10px;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:background .2s;
}
.nav-brand:hover .brand-icon{background:rgba(255,255,255,.18)}
.brand-text{display:flex;flex-direction:column}
.brand-name{font-size:1.2rem;font-weight:700;color:#fff;letter-spacing:-.3px;line-height:1}
.brand-sub{font-size:.68rem;color:rgba(255,255,255,.45);letter-spacing:.2px;margin-top:3px;font-weight:400}
/* links */
.nav-links{display:flex;align-items:center;gap:0;list-style:none;margin-left:2rem}
.nav-links a{
  color:rgba(255,255,255,.65);text-decoration:none;
  font-size:.875rem;font-weight:500;
  padding:.45rem 1rem;border-radius:6px;
  transition:color .2s,background .2s;
  letter-spacing:.1px;
}
.nav-links a:hover{color:#fff;background:rgba(255,255,255,.07)}
.nav-links a.active{color:#fff}
/* cta */
.nav-cta{
  background:transparent;
  border:1.5px solid rgba(255,255,255,.35);
  color:#fff;padding:.5rem 1.4rem;border-radius:8px;
  font-size:.875rem;font-weight:600;
  cursor:pointer;font-family:inherit;
  transition:background .2s,border-color .2s;
  letter-spacing:.1px;
}
.nav-cta:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.6)}
/* hamburger */
.nav-hamburger{
  display:none;flex-direction:column;gap:5px;cursor:pointer;
  background:none;border:none;padding:.4rem;
}
.nav-hamburger span{
  display:block;width:24px;height:2px;
  background:#fff;border-radius:2px;
  transition:transform .3s,opacity .3s;
}
.nav-hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.nav-hamburger.open span:nth-child(2){opacity:0}
.nav-hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
/* mobile menu */
.nav-mobile{
  display:none;position:fixed;top:76px;left:0;right:0;
  background:rgba(8,12,28,.97);backdrop-filter:blur(18px);
  border-top:1px solid rgba(255,255,255,.08);
  padding:1.25rem 2rem 1.75rem;
  flex-direction:column;gap:.25rem;
  transform:translateY(-10px);opacity:0;
  transition:transform .3s,opacity .3s;
  pointer-events:none;
}
.nav-mobile.open{display:flex;transform:translateY(0);opacity:1;pointer-events:all}
.nav-mobile a{
  color:rgba(255,255,255,.8);text-decoration:none;
  font-size:.975rem;font-weight:500;padding:.8rem 1rem;
  border-radius:8px;display:block;
  transition:background .2s,color .2s;
  border-bottom:1px solid rgba(255,255,255,.05);
}
.nav-mobile a:last-child{border-bottom:none}
.nav-mobile a:hover{background:rgba(255,255,255,.07);color:#fff}
.nav-mobile .mob-cta{
  margin-top:.75rem;
  background:rgba(255,255,255,.1);
  border:1.5px solid rgba(255,255,255,.25);
  color:#fff;text-align:center;font-weight:600;border-radius:10px;
  border-bottom:none;
}

/* ══════════════════════════════════════
   HERO
══════════════════════════════════════ */
.hero{position:relative;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden}
/* slider */
.hero-slider{position:absolute;inset:0;z-index:0}
.hero-slide{
  position:absolute;inset:0;
  background-size:cover;background-position:center;
  opacity:0;
  transition:opacity 1.6s ease;
  z-index:0;
}
.hero-slide.active{opacity:1;z-index:1}
.hero-overlay{position:absolute;inset:0;z-index:2;background:linear-gradient(160deg,rgba(4,8,24,.88) 0%,rgba(20,10,50,.72) 100%)}
/* dots */
.hero-dots{
  position:absolute;bottom:5rem;left:50%;transform:translateX(-50%);
  z-index:4;display:flex;gap:.6rem;
}
.hero-dot{
  width:8px;height:8px;border-radius:50%;
  background:rgba(255,255,255,.35);border:none;cursor:pointer;padding:0;
  transition:background .3s,transform .3s,width .3s;
}
.hero-dot.active{background:#fff;width:24px;border-radius:4px;}
.hero-content{position:relative;z-index:3;text-align:center;padding:2rem;max-width:860px;margin:0 auto}
.hero-badge{
  display:inline-flex;align-items:center;gap:.5rem;
  background:rgba(79,142,247,.15);border:1px solid rgba(79,142,247,.35);
  color:#93c5fd;padding:.45rem 1.1rem;border-radius:999px;
  font-size:.78rem;font-weight:600;letter-spacing:.6px;text-transform:uppercase;
  margin-bottom:2rem;backdrop-filter:blur(6px);
}
.hero h1{
  font-size:clamp(2.6rem,6.5vw,5.2rem);font-weight:900;
  color:#fff;line-height:1.08;letter-spacing:-2.5px;margin-bottom:1.5rem;
}
.hero h1 .grad{
  background:linear-gradient(135deg,#60a5fa 0%,#c084fc 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.hero p{font-size:1.15rem;color:rgba(255,255,255,.72);max-width:580px;margin:0 auto 2.75rem;line-height:1.75}
.hero-btns{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}
.btn-primary{
  background:linear-gradient(135deg,#4f8ef7,#7c3aed);color:#fff;
  padding:.95rem 2.1rem;border-radius:12px;font-weight:700;font-size:1rem;
  text-decoration:none;display:inline-flex;align-items:center;gap:.55rem;
  box-shadow:0 6px 24px rgba(79,142,247,.45);
  transition:transform .2s,box-shadow .2s;border:none;cursor:pointer;font-family:inherit;
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(79,142,247,.55)}
.btn-outline{
  background:rgba(255,255,255,.08);color:#fff;
  padding:.95rem 2.1rem;border-radius:12px;font-weight:600;font-size:1rem;
  text-decoration:none;display:inline-flex;align-items:center;gap:.55rem;
  border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(8px);
  transition:background .2s,transform .2s;
}
.btn-outline:hover{background:rgba(255,255,255,.16);transform:translateY(-2px)}
.hero-scroll{
  position:absolute;bottom:2.25rem;left:50%;transform:translateX(-50%);
  z-index:3;
  color:rgba(255,255,255,.35);font-size:.75rem;
  display:flex;flex-direction:column;align-items:center;gap:.4rem;
  animation:scrollBounce 2.2s ease-in-out infinite;
}
@keyframes scrollBounce{0%,100%{transform:translateX(-50%) translateY(0)}55%{transform:translateX(-50%) translateY(7px)}}

/* ══════════════════════════════════════
   STATS STRIP
══════════════════════════════════════ */
.stats-strip{background:linear-gradient(135deg,#080c1c,#12103a);padding:3.5rem 2rem}
.stats-inner{max-width:1320px;margin:0 auto;display:grid;grid-template-columns:repeat(5,1fr);gap:1.5rem}
.stat-item{
  text-align:center;padding:2rem 1.5rem;border-radius:18px;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  transition:transform .3s,background .3s,border-color .3s;
}
.stat-item:hover{transform:translateY(-5px);background:rgba(255,255,255,.08);border-color:rgba(79,142,247,.3)}
.stat-icon-wrap{width:54px;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem;font-size:1.35rem}
.stat-num{font-size:2.75rem;font-weight:900;color:#fff;letter-spacing:-1.5px;line-height:1}
.stat-lbl{font-size:.82rem;color:rgba(255,255,255,.5);margin-top:.5rem;font-weight:500;letter-spacing:.3px}

/* ══════════════════════════════════════
   SHARED SECTION
══════════════════════════════════════ */
.section{padding:6.5rem 2rem}
.section-inner{max-width:1320px;margin:0 auto}
.section-tag{
  display:inline-block;background:#ede9fe;color:#7c3aed;
  font-size:.72rem;font-weight:700;letter-spacing:.9px;text-transform:uppercase;
  padding:.35rem .95rem;border-radius:999px;margin-bottom:1rem;
}
.section-title{font-size:clamp(1.9rem,3.5vw,2.8rem);font-weight:800;letter-spacing:-1px;line-height:1.18;margin-bottom:1rem}
.section-sub{font-size:1.05rem;color:#64748b;max-width:560px;line-height:1.75}

/* ══════════════════════════════════════
   ABOUT — extended
══════════════════════════════════════ */
.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:5rem;align-items:center;margin-top:4rem}
.about-img-wrap{position:relative;border-radius:24px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.18)}
.about-img-wrap img{width:100%;height:440px;object-fit:cover;display:block;transition:transform .6s}
.about-img-wrap:hover img{transform:scale(1.04)}
.about-img-badge{
  position:absolute;bottom:1.5rem;left:1.5rem;
  background:rgba(8,12,28,.88);backdrop-filter:blur(12px);
  color:#fff;padding:.75rem 1.25rem;border-radius:12px;
  font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.14);
  display:flex;align-items:center;gap:.5rem;
}
.about-text p{color:#475569;line-height:1.85;margin-bottom:1.25rem;font-size:1rem}
.about-highlights{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:2rem}
.highlight-item{background:#f8fafc;border-radius:14px;padding:1.35rem;border-left:3px solid #4f8ef7;transition:transform .2s,box-shadow .2s}
.highlight-item:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.highlight-item i{color:#4f8ef7;margin-bottom:.6rem;font-size:1.15rem;display:block}
.highlight-item h4{font-size:.9rem;font-weight:700;margin-bottom:.3rem}
.highlight-item p{font-size:.8rem;color:#64748b;margin:0;line-height:1.5}

/* timeline */
.about-timeline{margin-top:4rem;padding-top:3.5rem;border-top:1px solid #e8edf5}
.timeline-header{text-align:center;margin-bottom:2.75rem}
.timeline-header h3{font-size:1.5rem;font-weight:800;letter-spacing:-.5px;color:#0f172a}
.timeline-header p{color:#64748b;font-size:.95rem;margin-top:.4rem}
.timeline{position:relative;max-width:860px;margin:0 auto}
.timeline::before{content:'';position:absolute;left:50%;top:0;bottom:0;width:2px;background:linear-gradient(to bottom,#4f8ef7,#7c3aed);transform:translateX(-50%)}
.tl-item{display:grid;grid-template-columns:1fr 1fr;gap:2.5rem;margin-bottom:2.5rem;position:relative}
.tl-item:nth-child(odd) .tl-content{grid-column:1;text-align:right}
.tl-item:nth-child(odd) .tl-spacer{grid-column:2}
.tl-item:nth-child(even) .tl-spacer{grid-column:1}
.tl-item:nth-child(even) .tl-content{grid-column:2;text-align:left}
.tl-dot{
  position:absolute;left:50%;top:1rem;transform:translateX(-50%);
  width:14px;height:14px;border-radius:50%;
  background:linear-gradient(135deg,#4f8ef7,#7c3aed);
  border:3px solid #fff;box-shadow:0 0 0 3px rgba(79,142,247,.25);
  z-index:1;
}
.tl-content{background:#f8fafc;border-radius:14px;padding:1.35rem 1.5rem;border:1px solid #e8edf5;transition:box-shadow .2s}
.tl-content:hover{box-shadow:0 8px 28px rgba(0,0,0,.08)}
.tl-year{font-size:.75rem;font-weight:700;color:#4f8ef7;letter-spacing:.8px;text-transform:uppercase;margin-bottom:.35rem}
.tl-content h4{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:.35rem}
.tl-content p{font-size:.82rem;color:#64748b;line-height:1.6;margin:0}

/* parish info band */
.parish-band{
  background:linear-gradient(135deg,#080c1c,#12103a);
  border-radius:20px;margin-top:3.5rem;padding:2.5rem 3rem;
  display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;
}
.parish-stat{text-align:center}
.parish-stat .ps-num{font-size:2rem;font-weight:900;color:#fff;letter-spacing:-1px}
.parish-stat .ps-lbl{font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.3rem;font-weight:500;letter-spacing:.3px}

@media(max-width:768px){
  .timeline::before{left:20px}
  .tl-item{grid-template-columns:1fr;padding-left:3rem}
  .tl-item:nth-child(odd) .tl-content,
  .tl-item:nth-child(even) .tl-content{grid-column:1;text-align:left}
  .tl-item:nth-child(odd) .tl-spacer,
  .tl-item:nth-child(even) .tl-spacer{display:none}
  .tl-dot{left:20px}
  .parish-band{grid-template-columns:1fr;gap:1.25rem}
}

/* ══════════════════════════════════════
   FEATURES
══════════════════════════════════════ */
.features-bg{background:#f8fafc}
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-top:3rem}
.feat-card{
  background:#fff;border-radius:20px;padding:2.1rem;
  border:1px solid #e8edf5;
  transition:transform .3s,box-shadow .3s;
  position:relative;overflow:hidden;
}
.feat-card::after{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,#4f8ef7,#7c3aed);
  transform:scaleX(0);transform-origin:left;transition:transform .35s;
}
.feat-card:hover{transform:translateY(-6px);box-shadow:0 20px 50px rgba(0,0,0,.09)}
.feat-card:hover::after{transform:scaleX(1)}
.feat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:1.25rem}
.feat-card h3{font-size:1.05rem;font-weight:700;margin-bottom:.6rem}
.feat-card p{font-size:.875rem;color:#64748b;line-height:1.65}

/* ══════════════════════════════════════
   GALLERY
══════════════════════════════════════ */
.gallery-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:3rem}
.gallery-item{border-radius:18px;overflow:hidden;aspect-ratio:4/3;position:relative;cursor:pointer}
.gallery-item img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.gallery-item:hover img{transform:scale(1.08)}
.gallery-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.65),transparent);opacity:0;transition:opacity .3s;display:flex;align-items:flex-end;padding:1.1rem}
.gallery-item:hover .gallery-overlay{opacity:1}
.gallery-overlay span{color:#fff;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.4rem}
.gallery-item.large{grid-column:span 2}
/* ══════════════════════════════════════
   CTA BAND
══════════════════════════════════════ */
.cta-band{
  background:linear-gradient(135deg,#080c1c,#12103a);
  padding:5rem 2rem;text-align:center;
}
.cta-band h2{font-size:clamp(1.8rem,3vw,2.6rem);font-weight:800;color:#fff;letter-spacing:-1px;margin-bottom:1rem}
.cta-band p{color:rgba(255,255,255,.65);font-size:1.05rem;max-width:520px;margin:0 auto 2.25rem;line-height:1.7}

/* ══════════════════════════════════════
   LOGIN MODAL
══════════════════════════════════════ */
.modal-backdrop{
  position:fixed;inset:0;z-index:2000;
  background:rgba(15,23,42,.55);
  backdrop-filter:blur(4px);
  display:flex;align-items:center;justify-content:center;
  padding:1.5rem;
  opacity:0;pointer-events:none;
  transition:opacity .25s;
}
.modal-backdrop.active{opacity:1;pointer-events:all}
.modal{
  background:#fff;border-radius:18px;width:100%;max-width:420px;
  box-shadow:0 24px 60px rgba(0,0,0,.18),0 4px 16px rgba(0,0,0,.08);
  transform:translateY(20px);opacity:0;
  transition:transform .3s ease,opacity .25s ease;
  position:relative;overflow:hidden;
}
.modal::before{
  content:'';display:block;height:4px;
  background:linear-gradient(90deg,#4f8ef7,#7c3aed);
}
.modal-backdrop.active .modal{transform:translateY(0);opacity:1}
.modal-close{
  position:absolute;top:1.1rem;right:1.1rem;
  width:32px;height:32px;border-radius:8px;
  background:none;border:none;cursor:pointer;
  color:#9ca3af;font-size:.95rem;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s,color .2s;
}
.modal-close:hover{background:#f1f5f9;color:#1e293b}
.modal-body{padding:2rem 2.25rem 2.25rem}
.modal-title{font-size:1.35rem;font-weight:700;color:#0f172a;margin-bottom:.3rem;letter-spacing:-.3px}
.modal-subtitle{font-size:.85rem;color:#94a3b8;margin-bottom:2rem;line-height:1.5}
.form-group{margin-bottom:1.25rem}
.form-group label{
  display:block;font-size:.8rem;font-weight:600;
  color:#475569;margin-bottom:.45rem;letter-spacing:.3px;text-transform:uppercase;
}
.form-group input{
  width:100%;padding:.8rem 1rem;
  border:1.5px solid #e2e8f0;border-radius:10px;
  font-size:.95rem;font-family:inherit;
  background:#f8fafc;color:#0f172a;
  transition:border-color .2s,box-shadow .2s,background .2s;
}
.form-group input:focus{
  outline:none;border-color:#4f8ef7;
  box-shadow:0 0 0 3px rgba(79,142,247,.12);
  background:#fff;
}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:2.75rem}
.eye-toggle{
  position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.9rem;
  transition:color .2s;padding:.2rem;
}
.eye-toggle:hover{color:#475569}
.error-box{
  background:#fef2f2;border:1px solid #fecaca;color:#dc2626;
  padding:.75rem 1rem;border-radius:8px;font-size:.83rem;
  margin-bottom:1.25rem;line-height:1.5;
}
.btn-submit{
  width:100%;padding:.9rem;margin-top:.75rem;
  background:#0f172a;
  color:#fff;border:none;border-radius:10px;
  font-size:.95rem;font-weight:600;cursor:pointer;font-family:inherit;
  letter-spacing:.2px;
  transition:background .2s,transform .15s;
}
.btn-submit:hover{background:#1e293b;transform:translateY(-1px)}
.btn-submit:active{transform:translateY(0)}
.modal-divider{
  display:flex;align-items:center;gap:.75rem;
  margin:1.25rem 0 0;color:#cbd5e1;font-size:.75rem;
}
.modal-divider::before,.modal-divider::after{content:'';flex:1;height:1px;background:#e2e8f0}
.modal-note{text-align:center;font-size:.78rem;color:#94a3b8;margin-top:.75rem}

/* ══════════════════════════════════════
   FOOTER
══════════════════════════════════════ */
footer{background:#060a18;color:rgba(255,255,255,.55);padding:4.5rem 2rem 2rem}
.footer-inner{max-width:1320px;margin:0 auto}
.footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:3rem;margin-bottom:3rem}
.footer-brand .fb-logo{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem}
.footer-brand .fb-logo span{color:#fff;font-size:1.2rem;font-weight:800}
.footer-brand p{font-size:.85rem;line-height:1.75;max-width:280px}
.footer-contact{margin-top:1.5rem}
.footer-contact p{font-size:.83rem;margin-bottom:.6rem;display:flex;align-items:center;gap:.55rem}
.footer-contact i{color:#4f8ef7;width:16px;flex-shrink:0}
.footer-col h5{color:#fff;font-size:.88rem;font-weight:700;margin-bottom:1.25rem;letter-spacing:.2px}
.footer-col ul{list-style:none}
.footer-col ul li{margin-bottom:.65rem}
.footer-col ul li a{
  color:rgba(255,255,255,.5);text-decoration:none;font-size:.83rem;
  display:flex;align-items:center;gap:.4rem;
  transition:color .2s,padding-left .2s;
}
.footer-col ul li a:hover{color:#fff;padding-left:4px}
.footer-col ul li a i{font-size:.65rem;color:#4f8ef7}
.footer-bottom{
  border-top:1px solid rgba(255,255,255,.07);padding-top:1.75rem;
  display:flex;justify-content:space-between;align-items:center;
  font-size:.78rem;flex-wrap:wrap;gap:1rem;
}
.footer-socials{display:flex;gap:.75rem}
.footer-socials a{
  width:36px;height:36px;background:rgba(255,255,255,.07);border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(255,255,255,.55);text-decoration:none;
  transition:background .2s,color .2s,transform .2s;
}
.footer-socials a:hover{background:#4f8ef7;color:#fff;transform:translateY(-2px)}

/* ══════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════ */
@media(max-width:1024px){
  .stats-inner{grid-template-columns:repeat(3,1fr)}
  .about-grid{grid-template-columns:1fr;gap:2.5rem}
  .features-grid{grid-template-columns:repeat(2,1fr)}
  .footer-top{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .nav-links,.nav-divider,.nav-cta{display:none}
  .nav-hamburger{display:flex}
  .hero h1{font-size:2.6rem;letter-spacing:-1.5px}
  .gallery-grid{grid-template-columns:1fr 1fr}
  .gallery-item.large{grid-column:span 1}
  .about-highlights{grid-template-columns:1fr}
  .footer-top{grid-template-columns:1fr}
}
@media(max-width:480px){
  .stats-inner{grid-template-columns:1fr 1fr}
  .features-grid{grid-template-columns:1fr}
  .gallery-grid{grid-template-columns:1fr}
  .hero-btns{flex-direction:column;align-items:center}
  .btn-primary,.btn-outline{width:100%;justify-content:center}
}
</style>
</head>
<body>

<!-- ══ LOGIN MODAL ══ -->
<div class="modal-backdrop <?php echo $open_modal ? 'active' : ''; ?>" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal">
    <button class="modal-close" id="modalClose" aria-label="Close"><i class="fas fa-times"></i></button>
    <div class="modal-body">
      <p class="modal-title" id="modalTitle">Sign In</p>
      <p class="modal-subtitle">Holy Spirit Parish Cemetery — Barcenaga, Naujan</p>

      <?php if ($error): ?>
      <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="" id="loginForm" autocomplete="off">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Enter your username"
            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autocomplete="off">
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="new-password">
            <button type="button" class="eye-toggle" id="eyeToggle" aria-label="Toggle password visibility">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
      </form>
      <div class="modal-divider">Authorized Personnel Only</div>
      <p class="modal-note">Access to this system is monitored and logged.</p>
    </div>
  </div>
</div>

<!-- ══ NAVBAR ══ -->
<nav class="nav" id="mainNav">
  <div class="nav-inner">
    <a href="#home" class="nav-brand">
      <div class="brand-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2L12 6M10 4h4"/>
          <path d="M3 10h18"/>
          <path d="M5 10v10a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1V10"/>
        </svg>
      </div>
      <div class="brand-text">
        <span class="brand-name">PeacePlot</span>
        <span class="brand-sub">Cemetery Management System</span>
      </div>
    </a>
    <ul class="nav-links">
      <li><a href="#home" class="active">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#features">Features</a></li>
      <li><a href="#gallery">Gallery</a></li>
    </ul>
    <button class="nav-cta" id="openLogin">Sign In</button>
    <button class="nav-hamburger" id="hamburger" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </div>
  <!-- mobile menu -->
  <div class="nav-mobile" id="mobileMenu">
    <a href="#home">Home</a>
    <a href="#about">About</a>
    <a href="#features">Features</a>
    <a href="#gallery">Gallery</a>
    <a href="#" class="mob-cta" id="openLoginMob">Sign In</a>
  </div>
</nav>

<!-- ══ HERO ══ -->
<section class="hero" id="home">
  <div class="hero-slider" id="heroSlider">
    <div class="hero-slide active" style="background-image:url('assets/images/Hero/cemetery1.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/cemetery2.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/cemetery3.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/cemetery4.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/cemetery6.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/cemetery7.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/Church1.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/Church2.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/Church3.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/Church4.jpg')"></div>
    <div class="hero-slide" style="background-image:url('assets/images/Hero/5.jpg')"></div>
  </div>
  <div class="hero-overlay"></div>
  <div class="hero-dots" id="heroDots"></div>
  <div class="hero-content">
    <div class="hero-badge">Barcenaga, Naujan &nbsp;·&nbsp; Oriental Mindoro</div>
    <h1>Honoring Memories,<br><span class="grad">Preserving History</span></h1>
    <p>The official cemetery management system of Holy Spirit Parish, Barcenaga, Naujan — digitally preserving burial records, mapping sacred grounds, and keeping family histories alive for generations.</p>
    <div class="hero-btns">
      <button class="btn-primary" id="openLoginHero">Access System</button>
      <a href="#about" class="btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-scroll"><span>Scroll</span><i class="fas fa-chevron-down"></i></div>
</section>

<!-- ══ ABOUT ══ -->
<section class="section" id="about">
  <div class="section-inner">
    <div class="about-grid">
      <div class="about-img-wrap">
        <img src="assets/images/Hero/Church1.jpg" alt="Holy Spirit Parish Church, Barcenaga">
        <div class="about-img-badge"><i class="fas fa-church"></i> Holy Spirit Parish, Barcenaga</div>
      </div>
      <div class="about-text">
        <span class="section-tag">Our Story</span>
        <h2 class="section-title">Sacred Grounds in the Heart of Naujan</h2>
        <p>Holy Spirit Parish is a Roman Catholic parish located in Barangay Barcenaga, Naujan, Oriental Mindoro — a municipality in the MIMAROPA region of the Philippines. The parish is under the pastoral care of the <strong style="color:#0f172a;font-weight:600">Apostolic Vicariate of Calapan</strong>, which oversees Catholic communities across Oriental Mindoro.</p>
        <p>The parish cemetery serves as a sacred resting place for generations of families from Barcenaga and surrounding barangays. Maintained and administered by the parish, it stands as a testament to the community's deep faith and reverence for the departed.</p>
        <p>To honor this legacy, PeacePlot was developed as a dedicated digital management system — ensuring every burial record, every plot, and every family history is preserved with the accuracy, care, and respect it deserves.</p>
        <div class="about-highlights">
          <div class="highlight-item"><i class="fas fa-church"></i><h4>Holy Spirit Parish</h4><p>Barcenaga, Naujan, Oriental Mindoro 5204</p></div>
          <div class="highlight-item"><i class="fas fa-cross"></i><h4>Apostolic Vicariate of Calapan</h4><p>Diocese overseeing the parish and its ministries</p></div>
          <div class="highlight-item"><i class="fas fa-users"></i><h4>Community Focused</h4><p>Serving families of Barcenaga and Naujan across generations</p></div>
          <div class="highlight-item"><i class="fas fa-shield-alt"></i><h4>Preserved Records</h4><p>Every burial digitally archived and secured</p></div>
        </div>
      </div>
    </div>

    <!-- Parish Stats Band -->
    <div class="parish-band">
      <div class="parish-stat">
        <div class="ps-num">IV-B</div>
        <div class="ps-lbl">MIMAROPA Region</div>
      </div>
      <div class="parish-stat">
        <div class="ps-num">5204</div>
        <div class="ps-lbl">ZIP Code, Naujan</div>
      </div>
      <div class="parish-stat">
        <div class="ps-num">109K+</div>
        <div class="ps-lbl">Naujan Population (2024)</div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="about-timeline">
      <div class="timeline-header">
        <h3>A History of Faith &amp; Service</h3>
        <p>Key milestones in the life of Holy Spirit Parish and its cemetery</p>
      </div>
      <div class="timeline">

        <div class="tl-item">
          <div class="tl-content">
            <div class="tl-year">17th Century</div>
            <h4>Recollect Missionaries Arrive</h4>
            <p>Augustinian Recollect missionaries establish Catholic communities across Naujan and Oriental Mindoro, laying the foundation for parish life in the region.</p>
          </div>
          <div class="tl-spacer"></div>
          <div class="tl-dot"></div>
        </div>

        <div class="tl-item">
          <div class="tl-spacer"></div>
          <div class="tl-content">
            <div class="tl-year">Early Parish Era</div>
            <h4>Holy Spirit Parish Established</h4>
            <p>Holy Spirit Parish in Barcenaga is formally established to serve the growing Catholic community of the barangay and its neighboring areas in Naujan.</p>
          </div>
          <div class="tl-dot"></div>
        </div>

        <div class="tl-item">
          <div class="tl-content">
            <div class="tl-year">Parish Growth</div>
            <h4>Cemetery Grounds Consecrated</h4>
            <p>The parish cemetery is consecrated and dedicated as a sacred resting place for the faithful of Barcenaga — a tradition of dignified burial maintained to this day.</p>
          </div>
          <div class="tl-spacer"></div>
          <div class="tl-dot"></div>
        </div>

        <div class="tl-item">
          <div class="tl-spacer"></div>
          <div class="tl-content">
            <div class="tl-year">Apostolic Vicariate</div>
            <h4>Under the Vicariate of Calapan</h4>
            <p>Holy Spirit Parish continues its ministry under the Apostolic Vicariate of Calapan, strengthening its pastoral programs and community outreach across Oriental Mindoro.</p>
          </div>
          <div class="tl-dot"></div>
        </div>

        <div class="tl-item">
          <div class="tl-content">
            <div class="tl-year">Present Day</div>
            <h4>PeacePlot Digital System Launched</h4>
            <p>The parish adopts PeacePlot — a modern cemetery management system — to digitally preserve burial records, manage cemetery lots, and serve families with greater efficiency and transparency.</p>
          </div>
          <div class="tl-spacer"></div>
          <div class="tl-dot"></div>
        </div>

      </div>
    </div>

  </div>
</section>

<!-- ══ FEATURES ══ -->
<section class="section features-bg" id="features">
  <div class="section-inner">
    <div style="text-align:center">
      <span class="section-tag">System Features</span>
      <h2 class="section-title" style="margin:0 auto .75rem">Everything You Need to Manage a Cemetery</h2>
      <p class="section-sub" style="margin:0 auto">A complete digital platform built for modern cemetery administration</p>
    </div>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon" style="background:#eff6ff"><i class="fas fa-database" style="color:#3b82f6"></i></div>
        <h3>Digital Burial Records</h3>
        <p>Fully searchable database of all burial records with complete personal details, dates, and plot assignments.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon" style="background:#f5f3ff"><i class="fas fa-map-marked-alt" style="color:#7c3aed"></i></div>
        <h3>Interactive Cemetery Map</h3>
        <p>Visual plot management with GPS coordinates, section layouts, and real-time lot availability tracking.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon" style="background:#ecfdf5"><i class="fas fa-camera" style="color:#10b981"></i></div>
        <h3>Image &amp; Media Archive</h3>
        <p>Upload and organize memorial photos, grave marker images, and historical documentation per burial record.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon" style="background:#fff7ed"><i class="fas fa-chart-bar" style="color:#f59e0b"></i></div>
        <h3>Reports &amp; Analytics</h3>
        <p>Generate detailed reports on lot availability, burial statistics, and historical trends with CSV export.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon" style="background:#fef2f2"><i class="fas fa-history" style="color:#ef4444"></i></div>
        <h3>Audit &amp; History Log</h3>
        <p>Full audit trail of all changes with version history, ensuring data integrity and accountability.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon" style="background:#f0fdf4"><i class="fas fa-search" style="color:#22c55e"></i></div>
        <h3>Universal Search</h3>
        <p>Instantly search across all records by name, date, section, block, or lot number from one interface.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ GALLERY ══ -->
<section class="section" id="gallery">
  <div class="section-inner">
    <span class="section-tag">Gallery</span>
    <h2 class="section-title">Our Grounds &amp; Facilities</h2>
    <div class="gallery-grid">
      <div class="gallery-item large">
        <img src="assets/images/Hero/cemetery1.jpg" alt="Cemetery Grounds">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Cemetery Grounds</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/Church1.jpg" alt="Historic Chapel">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Historic Chapel</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/Church2.jpg" alt="Church Exterior">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Church Exterior</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/cemetery2.jpg" alt="Cemetery Sections">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Cemetery Sections</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/cemetery3.jpg" alt="Burial Grounds">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Burial Grounds</span></div>
      </div>
      <div class="gallery-item large">
        <img src="assets/images/Hero/Church3.jpg" alt="Chapel Interior">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Chapel Interior</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/cemetery4.jpg" alt="Memorial Area">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Memorial Area</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/Church4.jpg" alt="Church Grounds">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Church Grounds</span></div>
      </div>
      <div class="gallery-item">
        <img src="assets/images/Hero/cemetery6.jpg" alt="Peaceful Grounds">
        <div class="gallery-overlay"><span><i class="fas fa-expand-alt"></i> Peaceful Grounds</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ══ STATS ══ -->
<section class="stats-strip">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-icon-wrap" style="background:rgba(79,142,247,.15)"><i class="fas fa-cross" style="color:#60a5fa"></i></div>
      <div class="stat-num"><?php echo number_format($stats['total_burials']); ?></div>
      <div class="stat-lbl">Burial Records</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon-wrap" style="background:rgba(124,58,237,.15)"><i class="fas fa-map-marker-alt" style="color:#a78bfa"></i></div>
      <div class="stat-num"><?php echo number_format($stats['total_lots']); ?></div>
      <div class="stat-lbl">Cemetery Lots</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon-wrap" style="background:rgba(16,185,129,.15)"><i class="fas fa-layer-group" style="color:#34d399"></i></div>
      <div class="stat-num"><?php echo number_format($stats['total_sections']); ?></div>
      <div class="stat-lbl">Sections</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon-wrap" style="background:rgba(236,72,153,.15)"><i class="fas fa-th-large" style="color:#f472b6"></i></div>
      <div class="stat-num"><?php echo number_format($stats['total_blocks']); ?></div>
      <div class="stat-lbl">Blocks</div>
    </div>
    <div class="stat-item">
      <div class="stat-icon-wrap" style="background:rgba(245,158,11,.15)"><i class="fas fa-images" style="color:#fbbf24"></i></div>
      <div class="stat-num"><?php echo number_format($stats['total_images']); ?></div>
      <div class="stat-lbl">Memorial Images</div>
    </div>
  </div>
</section>

<!-- ══ CTA BAND ══ -->
<section class="cta-band">
  <h2>Ready to Access the System?</h2>
  <p>Authorized parish staff can log in to manage burial records, update plots, and generate reports from anywhere.</p>
  <button class="btn-primary" id="openLoginCta">Sign In</button>
</section>

<!-- ══ FOOTER ══ -->
<footer>
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="fb-logo">
          <div class="brand-icon" style="width:38px;height:38px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2L12 6M10 4h4"/><path d="M3 10h18"/><path d="M5 10v10a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1V10"/>
            </svg>
          </div>
          <span>PeacePlot</span>
        </div>
        <p>The official cemetery management system of Holy Spirit Parish, Barcenaga, Naujan — under the Apostolic Vicariate of Calapan.</p>
        <div class="footer-contact">
          <p><i class="fas fa-map-marker-alt"></i> Barcenaga, Naujan, Oriental Mindoro 5204</p>
          <p><i class="fas fa-church"></i> Apostolic Vicariate of Calapan</p>
          <p><i class="fas fa-globe"></i> Region IV-B (MIMAROPA)</p>
          <p><i class="fas fa-clock"></i> Mon–Sat: 8:00 AM – 5:00 PM</p>
        </div>
      </div>
      <div class="footer-col">
        <h5>Quick Links</h5>
        <ul>
          <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
          <li><a href="#about"><i class="fas fa-chevron-right"></i> About Us</a></li>
          <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
          <li><a href="#gallery"><i class="fas fa-chevron-right"></i> Gallery</a></li>
          <li><a href="#" id="openLoginFooter"><i class="fas fa-chevron-right"></i> Staff Login</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Services</h5>
        <ul>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Burial Services</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Plot Management</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Historical Records</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Memorial Services</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Lot Availability</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>System</h5>
        <ul>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Burial Records</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Cemetery Map</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Reports</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Image Archive</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Settings</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> Holy Spirit Parish Cemetery Management System — Barcenaga, Naujan, Oriental Mindoro. All rights reserved.</span>
      <div class="footer-socials">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
      </div>
    </div>
  </div>
</footer>

<script>
(function(){
  /* ── Hero Slider ── */
  (function(){
    const slides   = Array.from(document.querySelectorAll('.hero-slide'));
    const dotsWrap = document.getElementById('heroDots');
    if(!slides.length) return;

    let cur = 0;
    let timer = null;

    // build dots
    slides.forEach(function(_, i){
      const d = document.createElement('button');
      d.className = 'hero-dot' + (i === 0 ? ' active' : '');
      d.setAttribute('aria-label', 'Slide ' + (i+1));
      d.addEventListener('click', function(){ goTo(i); });
      dotsWrap.appendChild(d);
    });

    function goTo(n){
      slides[cur].classList.remove('active');
      dotsWrap.children[cur].classList.remove('active');
      cur = (n + slides.length) % slides.length;
      slides[cur].classList.add('active');
      dotsWrap.children[cur].classList.add('active');
    }

    function startTimer(){
      if(timer) clearInterval(timer);
      timer = setInterval(function(){ goTo(cur + 1); }, 5000);
    }

    // always running — restart whenever tab becomes visible again
    document.addEventListener('visibilitychange', function(){
      if(document.hidden){ clearInterval(timer); timer = null; }
      else { startTimer(); }
    });

    startTimer();
  })();

  /* ── Modal ── */
  const modal   = document.getElementById('loginModal');
  const closBtn = document.getElementById('modalClose');

  function openModal(e){ if(e) e.preventDefault(); modal.classList.add('active'); document.body.style.overflow='hidden'; }
  function closeModal(){ modal.classList.remove('active'); document.body.style.overflow=''; }

  ['openLogin','openLoginHero','openLoginCta','openLoginMob','openLoginFooter'].forEach(id=>{
    const el = document.getElementById(id);
    if(el) el.addEventListener('click', openModal);
  });
  closBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

  // Auto-open if there was a login error (PHP set open_modal)
  <?php if($open_modal): ?>modal.classList.add('active'); document.body.style.overflow='hidden';<?php endif; ?>

  /* ── Password toggle ── */
  const pwInput = document.getElementById('password');
  const eyeBtn  = document.getElementById('eyeToggle');
  const eyeIcon = document.getElementById('eyeIcon');
  eyeBtn.addEventListener('click',()=>{
    const show = pwInput.type==='password';
    pwInput.type = show ? 'text' : 'password';
    eyeIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
  });

  /* ── Navbar scroll ── */
  const nav = document.getElementById('mainNav');
  function handleScroll(){
    nav.classList.toggle('scrolled', window.scrollY > 30);
    // active link
    const sections = ['home','about','features','gallery'];
    let current = 'home';
    sections.forEach(id=>{
      const el = document.getElementById(id);
      if(el && window.scrollY >= el.offsetTop - 120) current = id;
    });
    document.querySelectorAll('.nav-links a').forEach(a=>{
      a.classList.toggle('active', a.getAttribute('href')==='#'+current);
    });
  }
  window.addEventListener('scroll', handleScroll, {passive:true});
  handleScroll();

  /* ── Hamburger ── */
  const burger  = document.getElementById('hamburger');
  const mobMenu = document.getElementById('mobileMenu');
  burger.addEventListener('click',()=>{
    burger.classList.toggle('open');
    mobMenu.classList.toggle('open');
  });
  mobMenu.querySelectorAll('a:not(#openLoginMob)').forEach(a=>{
    a.addEventListener('click',()=>{ burger.classList.remove('open'); mobMenu.classList.remove('open'); });
  });

  /* ── Stats count-up ── */
  const counters = document.querySelectorAll('.stat-num');
  const cObs = new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.textContent.replace(/,/g,''))||0;
      if(!target){ cObs.unobserve(el); return; }
      let cur=0; const step=Math.ceil(target/60);
      const t=setInterval(()=>{ cur=Math.min(cur+step,target); el.textContent=cur.toLocaleString(); if(cur>=target) clearInterval(t); },18);
      cObs.unobserve(el);
    });
  },{threshold:.5});
  counters.forEach(c=>cObs.observe(c));

  /* ── Fade-in on scroll ── */
  const fadeEls = document.querySelectorAll('.feat-card,.stat-item,.highlight-item,.gallery-item,.about-img-wrap');
  const fObs = new IntersectionObserver(entries=>{
    entries.forEach(e=>{ if(e.isIntersecting){ e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; } });
  },{threshold:.08});
  fadeEls.forEach(el=>{
    el.style.opacity='0'; el.style.transform='translateY(22px)';
    el.style.transition='opacity .55s ease, transform .55s ease';
    fObs.observe(el);
  });
})();
</script>
</body>
</html>
