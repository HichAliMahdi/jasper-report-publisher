<?php
    session_start(['read_and_close' => true]);
    require('incl/const.php');
    require('incl/jru-lib.php');
    
    if(!isset($_SESSION[SESS_USR_KEY]) || !in_array($_SESSION[SESS_USR_KEY]->accesslevel, ADMINISTRATION_ACCESS) ){
        header('Location: ../login.php');
        exit;
    }
    
    $diagnostics = [];
    
    // Check 1: Tomcat status
    $tomcat_status = shell_exec('systemctl is-active tomcat 2>&1');
    $diagnostics['tomcat_running'] = [
        'name' => 'Tomcat Service',
        'status' => trim($tomcat_status) === 'active',
        'message' => trim($tomcat_status) === 'active' ? 'Running' : 'Not running: ' . trim($tomcat_status),
        'fix' => 'sudo systemctl start tomcat'
    ];
    
    // Check 2: Port 8080 accessible
    $port_check = @fsockopen('localhost', 8080, $errno, $errstr, 5);
    $diagnostics['port_8080'] = [
        'name' => 'Port 8080 Accessible',
        'status' => $port_check !== false,
        'message' => $port_check !== false ? 'Port 8080 is open' : 'Port 8080 is not accessible',
        'fix' => 'Check firewall: sudo ufw allow 8080'
    ];
    if($port_check) fclose($port_check);
    
    // Check 3: CATALINA_HOME set
    $catalina_home = get_catalina_home();
    $diagnostics['catalina_home'] = [
        'name' => 'CATALINA_HOME',
        'status' => !empty($catalina_home) && is_dir($catalina_home),
        'message' => !empty($catalina_home) ? $catalina_home : 'Not set',
        'fix' => 'Set in /etc/environment: CATALINA_HOME=/path/to/tomcat'
    ];
    
    // Check 4: JRI WAR deployed
    $jri_war_path = $catalina_home . '/webapps/JasperReportsIntegration.war';
    $jri_deployed_path = $catalina_home . '/webapps/JasperReportsIntegration';
    $diagnostics['jri_war'] = [
        'name' => 'JasperReportsIntegration WAR',
        'status' => file_exists($jri_war_path) || is_dir($jri_deployed_path),
        'message' => file_exists($jri_war_path) ? 'WAR file exists' : (is_dir($jri_deployed_path) ? 'Deployed' : 'Not found'),
        'fix' => 'Run installer: cd installer && sudo bash jri-install.sh'
    ];
    
    // Check 5: JRI endpoint accessible
    $jri_url = 'http://localhost:8080/JasperReportsIntegration/';
    $ch = curl_init($jri_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $diagnostics['jri_endpoint'] = [
        'name' => 'JRI Endpoint',
        'status' => $http_code >= 200 && $http_code < 400,
        'message' => $http_code >= 200 && $http_code < 400 ? 'Accessible (HTTP ' . $http_code . ')' : 'Not accessible (HTTP ' . $http_code . ')',
        'fix' => 'Restart Tomcat: sudo systemctl restart tomcat'
    ];
    
    // Check 6: gen_jri_report.sh script exists
    $jri_script = '/usr/local/bin/gen_jri_report.sh';
    $diagnostics['jri_script'] = [
        'name' => 'Report Generation Script',
        'status' => file_exists($jri_script) && is_executable($jri_script),
        'message' => file_exists($jri_script) ? (is_executable($jri_script) ? 'Exists and executable' : 'Exists but not executable') : 'Not found',
        'fix' => 'Copy from installer: sudo cp installer/gen_jri_report.sh /usr/local/bin/ && sudo chmod +x /usr/local/bin/gen_jri_report.sh'
    ];
    
    // Check 7: Jasper reports directory
    $jasper_home = get_jasper_home();
    $diagnostics['jasper_home'] = [
        'name' => 'Jasper Reports Directory',
        'status' => !empty($jasper_home) && is_dir($jasper_home),
        'message' => !empty($jasper_home) && is_dir($jasper_home) ? $jasper_home : 'Not found',
        'fix' => 'Create: sudo mkdir -p ' . $catalina_home . '/jasper_reports'
    ];
    
    // Check 8: Reports directory writable
    $reports_dir = $jasper_home . '/reports';
    $diagnostics['reports_writable'] = [
        'name' => 'Reports Directory Writable',
        'status' => is_dir($reports_dir) && is_writable($reports_dir),
        'message' => is_dir($reports_dir) ? (is_writable($reports_dir) ? 'Writable' : 'Not writable') : 'Does not exist',
        'fix' => 'Fix permissions: sudo chown -R www-data:www-data ' . $reports_dir
    ];
    
    $all_passed = true;
    foreach($diagnostics as $check) {
        if(!$check['status']) {
            $all_passed = false;
            break;
        }
    }
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <?php include("incl/meta.php"); ?>
    <link href="dist/css/admin.css" rel="stylesheet">
    <style>
        .diagnostic-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .diagnostic-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .diagnostic-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .diagnostic-header h2 {
            margin: 0;
            color: #1f2937;
        }
        
        .overall-status {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .overall-status.pass {
            background: #d1fae5;
            color: #065f46;
        }
        
        .overall-status.fail {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .check-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .check-item.pass {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        
        .check-item.fail {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        
        .check-icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .check-item.pass .check-icon {
            background: #10b981;
            color: white;
        }
        
        .check-item.fail .check-icon {
            background: #ef4444;
            color: white;
        }
        
        .check-content {
            flex: 1;
        }
        
        .check-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .check-message {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .check-fix {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #374151;
            margin-top: 8px;
        }
        
        .refresh-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .refresh-btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="full"
        data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full">

        <?php 
        define('MENU_SEL', 'diagnostics.php');
        include("incl/topbar.php");
        include("incl/sidebar.php");
        ?>

        <div class="page-wrapper">
            <div class="diagnostic-container">
                <div class="diagnostic-card">
                    <div class="diagnostic-header">
                        <h2><i class="material-icons" style="vertical-align: middle; margin-right: 10px;">settings_suggest</i> System Diagnostics</h2>
                        <div>
                            <span class="overall-status <?= $all_passed ? 'pass' : 'fail' ?>">
                                <?= $all_passed ? '✓ All Checks Passed' : '✗ Issues Found' ?>
                            </span>
                            <button class="refresh-btn" onclick="location.reload()" style="margin-left: 10px;">
                                <i class="material-icons" style="vertical-align: middle; font-size: 18px;">refresh</i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <?php foreach($diagnostics as $key => $check): ?>
                    <div class="check-item <?= $check['status'] ? 'pass' : 'fail' ?>">
                        <div class="check-icon">
                            <i class="material-icons"><?= $check['status'] ? 'check' : 'close' ?></i>
                        </div>
                        <div class="check-content">
                            <div class="check-name"><?= htmlspecialchars($check['name']) ?></div>
                            <div class="check-message"><?= htmlspecialchars($check['message']) ?></div>
                            <?php if(!$check['status']): ?>
                            <div class="check-fix">
                                <strong>Fix:</strong> <?= htmlspecialchars($check['fix']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(!$all_passed): ?>
                <div class="diagnostic-card" style="border-left: 4px solid #f59e0b;">
                    <h3 style="color: #f59e0b; margin-top: 0;">
                        <i class="material-icons" style="vertical-align: middle;">help_outline</i> Quick Fix Steps
                    </h3>
                    <ol style="line-height: 2;">
                        <li>Check if Tomcat is running: <code>sudo systemctl status tomcat</code></li>
                        <li>Start Tomcat if needed: <code>sudo systemctl start tomcat</code></li>
                        <li>Check Tomcat logs: <code>sudo tail -f <?= $catalina_home ?>/logs/catalina.out</code></li>
                        <li>Verify JRI is deployed: <code>ls -la <?= $catalina_home ?>/webapps/ | grep Jasper</code></li>
                        <li>Test JRI endpoint: <code>curl -I http://localhost:8080/JasperReportsIntegration/</code></li>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="dist/js/sidebarmenu.js"></script>
    <script src="dist/js/custom.js"></script>
</body>
</html>
