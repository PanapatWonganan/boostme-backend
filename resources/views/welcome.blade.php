<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Welcome</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                rgba(236, 72, 153, 0.1) 0%, 
                transparent 30%, 
                rgba(59, 130, 246, 0.1) 70%, 
                transparent 100%
            );
            animation: rotateBackground 20s linear infinite;
        }

        @keyframes rotateBackground {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Main Container */
        .welcome-container {
            text-align: center;
            color: white;
            max-width: 500px;
            padding: 40px 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideIn 1s ease-out;
        }

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Logo */
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: rgba(236, 72, 153, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        /* Typography */
        .app-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ec4899, #f43f5e);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: glow 3s ease-in-out infinite alternate;
        }

        @keyframes glow {
            0% { text-shadow: 0 0 10px rgba(236, 72, 153, 0.5); }
            100% { text-shadow: 0 0 20px rgba(244, 63, 94, 0.8); }
        }

        .tagline {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 0.9;
                transform: translateY(0);
            }
        }

        /* Button */
        .dashboard-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            background: linear-gradient(45deg, #ec4899, #f43f5e);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
            animation: fadeInUp 1s ease-out 1s both;
            position: relative;
            overflow: hidden;
        }

        .dashboard-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .dashboard-btn:hover::before {
            left: 100%;
        }

        .dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(236, 72, 153, 0.4);
        }

        .dashboard-btn:active {
            transform: translateY(0);
        }

        /* Features List */
        .features {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 1.5s both;
        }

        .feature {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .feature:hover {
            opacity: 1;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .feature-text {
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .welcome-container {
                margin: 20px;
                padding: 30px 20px;
            }

            .app-name {
                font-size: 2rem;
            }

            .features {
                gap: 20px;
            }

            .dashboard-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>

    <!-- Main Content -->
    <div class="welcome-container">
        <!-- Logo -->
        <div class="logo">
            <svg viewBox="0 0 24 24">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        </div>

        <!-- App Name -->
        <h1 class="app-name">{{ config('app.name', 'BoostMe Admin') }}</h1>
        
        <!-- Tagline -->
        <p class="tagline">ระบบจัดการ Learning Management System<br>สำหรับ Fitness & Wellness</p>

        <!-- Dashboard Button -->
        <a href="/admin" class="dashboard-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
            </svg>
            เข้าสู่ Dashboard
        </a>

        <!-- Features -->
        <div class="features">
            <div class="feature">
                <div class="feature-icon">📊</div>
                <div class="feature-text">Analytics</div>
            </div>
            <div class="feature">
                <div class="feature-icon">👥</div>
                <div class="feature-text">Users</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📚</div>
                <div class="feature-text">Courses</div>
            </div>
            <div class="feature">
                <div class="feature-icon">💰</div>
                <div class="feature-text">Revenue</div>
            </div>
        </div>
    </div>

    <script>
        // Add click animation to dashboard button
        document.addEventListener('DOMContentLoaded', function() {
            const dashboardBtn = document.querySelector('.dashboard-btn');
            dashboardBtn.addEventListener('click', function(e) {
                // Create ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = \`
                    position: absolute;
                    width: \${size}px;
                    height: \${size}px;
                    left: \${x}px;
                    top: \${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                \`;
                
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add ripple animation CSS
        const style = document.createElement('style');
        style.textContent = \`
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        \`;
        document.head.appendChild(style);
    </script>
</body>
</html>