<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Event Management System</title>
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
            padding: 1rem;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 800px;
            max-height: 95vh;
            overflow-y: auto;
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .register-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
        }

        .register-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .register-body {
            padding: 1.5rem;
        }

        /* Role Selection Step */
        .role-selection {
            display: block;
        }

        .role-selection-title {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .role-selection-title h2 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 0.3rem;
        }

        .role-selection-title p {
            color: #666;
            font-size: 0.9rem;
        }

        .role-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .role-card {
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .role-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .role-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
        }

        .role-card h3 {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 0.4rem;
        }

        .role-card p {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .role-features {
            list-style: none;
            margin-top: 0.8rem;
            font-size: 0.8rem;
            color: #555;
        }

        .role-features li {
            margin-bottom: 0.25rem;
        }

        .role-features li:before {
            content: "✓ ";
            color: #667eea;
            font-weight: bold;
        }

        .btn-continue {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Registration Form Step */
        .registration-form {
            display: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .selected-role-badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .btn-back {
            padding: 0.8rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 0.8rem;
        }

        .btn-back:hover {
            background: #667eea;
            color: white;
        }

        .btn-register {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .form-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.4rem;
        }

        .password-requirements ul {
            margin-left: 1.2rem;
            margin-top: 0.25rem;
        }

        .login-link {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            margin-top: 1rem;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.3s;
        }

        .login-link a:hover {
            opacity: 0.7;
        }

        .back-home {
            text-align: center;
            margin-top: 1rem;
        }

        .back-home a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.85rem;
            transition: opacity 0.3s;
        }

        .back-home a:hover {
            opacity: 0.7;
        }

        .alert {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 640px) {
            body {
                padding: 0.5rem;
            }

            .register-container {
                border-radius: 15px;
                max-height: 98vh;
            }

            .register-header {
                padding: 1rem;
            }

            .register-header h1 {
                font-size: 1.25rem;
            }

            .register-header p {
                font-size: 0.8rem;
            }

            .register-body {
                padding: 1rem;
            }

            .role-selection-title h2 {
                font-size: 1.1rem;
            }

            .role-selection-title p {
                font-size: 0.8rem;
            }

            .role-cards {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }

            .role-card {
                padding: 1.2rem;
            }

            .role-icon {
                font-size: 2rem;
            }

            .role-card h3 {
                font-size: 1rem;
            }

            .role-card p {
                font-size: 0.8rem;
            }

            .role-features {
                font-size: 0.75rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0.8rem;
                margin-bottom: 0.8rem;
            }

            .form-group {
                margin-bottom: 0.8rem;
            }

            .form-group label {
                font-size: 0.85rem;
            }

            .form-group input {
                padding: 0.65rem;
                font-size: 0.9rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 0.6rem;
            }

            .btn-back {
                margin-right: 0;
                width: 100%;
            }

            .btn-register {
                width: 100%;
            }

            .password-requirements {
                font-size: 0.75rem;
            }

            .selected-role-badge {
                font-size: 0.8rem;
                padding: 0.35rem 0.8rem;
            }
        }

        /* Tablet Responsive Styles */
        @media (min-width: 641px) and (max-width: 1024px) {
            .register-container {
                max-width: 700px;
            }

            .role-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Small height screens */
        @media (max-height: 700px) {
            .register-header h1 {
                font-size: 1.2rem;
            }
            
            .register-header p {
                font-size: 0.8rem;
            }

            .role-icon {
                font-size: 2rem;
            }

            .role-features {
                font-size: 0.75rem;
            }

            .form-group {
                margin-bottom: 0.8rem;
            }

            .form-row {
                margin-bottom: 0.8rem;
            }
        }

        /* Touch device hover adjustments */
        @media (hover: none) {
            .role-card:hover {
                transform: none;
            }

            .btn-continue:hover,
            .btn-register:hover {
                transform: none;
            }

            .btn-back:hover {
                background: white;
                color: #667eea;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🎯 Create Your Account</h1>
            <p>Join our event management platform</p>
        </div>

        <div class="register-body">
            <!-- Step 1: Role Selection -->
            <div class="role-selection" id="roleSelectionStep">
                <div class="role-selection-title">
                    <h2>Choose Your Role</h2>
                    <p>Select how you want to use the platform</p>
                </div>

                <div class="role-cards">
                    <div class="role-card" onclick="selectRole('attendee')">
                        <input type="radio" name="role" value="attendee" id="roleAttendee">
                        <div class="role-icon">👤</div>
                        <h3>Attendee</h3>
                        <p>Join and participate in events</p>
                        <ul class="role-features">
                            <li>Browse events</li>
                            <li>Register for events</li>
                            <li>Receive confirmations</li>
                            <li>Track registrations</li>
                        </ul>
                    </div>

                    <div class="role-card" onclick="selectRole('organizer')">
                        <input type="radio" name="role" value="organizer" id="roleOrganizer">
                        <div class="role-icon">👨‍💼</div>
                        <h3>Organizer</h3>
                        <p>Create and manage events</p>
                        <ul class="role-features">
                            <li>Create events</li>
                            <li>Manage attendees</li>
                            <li>Generate reports</li>
                            <li>Track attendance</li>
                        </ul>
                    </div>
                </div>

                <button type="button" class="btn-continue" id="btnContinue" onclick="continueToForm()" disabled>
                    Continue to Registration
                </button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>

                <div class="back-home">
                    <a href="index.php">← Back to Home</a>
                </div>
            </div>

            <!-- Step 2: Registration Form -->
            <div class="registration-form" id="registrationFormStep">
                <div class="selected-role-badge" id="selectedRoleBadge"></div>

                <div class="alert alert-error" id="errorAlert"></div>

                <form id="registerForm" method="POST" action="process_registration.php">
                    <input type="hidden" name="role" id="selectedRole">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="first_name" placeholder="Enter first name" required>
                        </div>

                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="last_name" placeholder="Enter last name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-row" id="contactOrgRow">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="Enter phone number">
                        </div>

                        <div class="form-group" id="organizationGroup">
                            <label for="organization">Organization</label>
                            <input type="text" id="organization" name="organization" placeholder="Enter organization">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" placeholder="Create password" required>
                            <div class="password-requirements">
                                <ul>
                                    <li>At least 8 characters</li>
                                    <li>One uppercase letter</li>
                                    <li>One number</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password *</label>
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="terms" id="terms" required>
                            <span>I agree to the Terms and Conditions</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-back" onclick="backToRoleSelection()">
                            ← Back
                        </button>
                        <button type="submit" class="btn-register" style="flex: 1;">
                            Create Account
                        </button>
                    </div>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedRoleValue = '';

        function selectRole(role) {
            // Remove selected class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');

            // Check the radio button
            document.getElementById('role' + role.charAt(0).toUpperCase() + role.slice(1)).checked = true;

            // Store selected role
            selectedRoleValue = role;

            // Enable continue button
            document.getElementById('btnContinue').disabled = false;
        }

        function continueToForm() {
            if (!selectedRoleValue) {
                alert('Please select a role');
                return;
            }

            // Hide role selection, show registration form
            document.getElementById('roleSelectionStep').style.display = 'none';
            document.getElementById('registrationFormStep').style.display = 'block';

            // Update hidden input and badge
            document.getElementById('selectedRole').value = selectedRoleValue;
            const roleName = selectedRoleValue.charAt(0).toUpperCase() + selectedRoleValue.slice(1);
            const roleIcon = selectedRoleValue === 'attendee' ? '👤' : '👨‍💼';
            document.getElementById('selectedRoleBadge').textContent = roleIcon + ' ' + roleName;

            // Hide organization field for attendees
            const orgGroup = document.getElementById('organizationGroup');
            const contactOrgRow = document.getElementById('contactOrgRow');
            
            if (selectedRoleValue === 'attendee') {
                orgGroup.style.display = 'none';
            } else {
                orgGroup.style.display = 'block';
            }
        }

        function backToRoleSelection() {
            document.getElementById('roleSelectionStep').style.display = 'block';
            document.getElementById('registrationFormStep').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const email = document.getElementById('email').value;
            const terms = document.getElementById('terms').checked;
            const errorAlert = document.getElementById('errorAlert');

            // Check if passwords match
            if (password !== confirmPassword) {
                e.preventDefault();
                errorAlert.textContent = 'Passwords do not match';
                errorAlert.style.display = 'block';
                return false;
            }

            // Password strength validation
            if (password.length < 8) {
                e.preventDefault();
                errorAlert.textContent = 'Password must be at least 8 characters';
                errorAlert.style.display = 'block';
                return false;
            }

            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                errorAlert.textContent = 'Password must contain at least one uppercase letter';
                errorAlert.style.display = 'block';
                return false;
            }

            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                errorAlert.textContent = 'Password must contain at least one number';
                errorAlert.style.display = 'block';
                return false;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                errorAlert.textContent = 'Please enter a valid email address';
                errorAlert.style.display = 'block';
                return false;
            }

            // Terms validation
            if (!terms) {
                e.preventDefault();
                errorAlert.textContent = 'Please accept the Terms and Conditions';
                errorAlert.style.display = 'block';
                return false;
            }
        });

        // Hide error on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                document.getElementById('errorAlert').style.display = 'none';
            });
        });
    </script>
</body>
</html>