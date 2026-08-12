<?php
// Page variables
$current_page = 'admin';
$page_title = 'HiveNest Admin - Contact Submissions | Neural Command Center';
$page_description = 'HiveNest Admin Panel - Monitor and manage contact form submissions from potential clients.';
$page_keywords = 'admin panel, contact submissions, neural command center, hivenest administration';

// Page-specific JavaScript
$page_scripts = "
// Admin Panel Functionality
class AdminPanel {
    constructor() {
        this.submissions = [];
        this.init();
    }

    init() {
        this.loadSubmissions();
        // Auto-refresh every 30 seconds
        setInterval(() => this.loadSubmissions(), 30000);
    }

    async loadSubmissions() {
        this.showLoading();
        
        try {
            const response = await fetch('/api/contact');
            
            if (!response.ok) {
                throw new Error(\`HTTP \${response.status}: \${response.statusText}\`);
            }
            
            this.submissions = await response.json();
            this.renderSubmissions();
            
        } catch (error) {
            console.error('Failed to load submissions:', error);
            this.showError();
        }
    }

    showLoading() {
        document.getElementById('loading').style.display = 'block';
        document.getElementById('error').style.display = 'none';
        document.getElementById('submissions-container').style.display = 'none';
        document.getElementById('empty-state').style.display = 'none';
    }

    showError() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error').style.display = 'block';
        document.getElementById('submissions-container').style.display = 'none';
        document.getElementById('empty-state').style.display = 'none';
    }

    renderSubmissions() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('error').style.display = 'none';
        
        const container = document.getElementById('submissions-container');
        const emptyState = document.getElementById('empty-state');
        
        if (this.submissions.length === 0) {
            container.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        container.style.display = 'grid';
        emptyState.style.display = 'none';
        
        container.innerHTML = this.submissions.map(submission => \`
            <div class=\"cyber-card\">
                <div style=\"display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;\">
                    <h3 style=\"color: var(--cyber-neon-cyan); margin: 0;\">
                        \${submission.firstName} \${submission.lastName}
                    </h3>
                    <span class=\"status-badge \${submission.status}\" style=\"
                        padding: 0.25rem 0.75rem;
                        border-radius: 20px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        background: \${submission.status === 'new' ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-orange)'};
                        color: var(--cyber-black);
                    \">
                        \${submission.status}
                    </span>
                </div>
                
                <div style=\"margin-bottom: 1.5rem;\">
                    <div style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;\">
                        <div>
                            <strong style=\"color: var(--cyber-neon-pink);\">Email:</strong><br>
                            <a href=\"mailto:\${submission.email}\" style=\"color: var(--cyber-neon-cyan);\">\${submission.email}</a>
                        </div>
                        \${submission.phone ? \`
                        <div>
                            <strong style=\"color: var(--cyber-neon-pink);\">Phone:</strong><br>
                            <a href=\"tel:\${submission.phone}\" style=\"color: var(--cyber-neon-cyan);\">\${submission.phone}</a>
                        </div>
                        \` : ''}
                    </div>
                    
                    \${submission.company ? \`
                    <div style=\"margin-bottom: 1rem;\">
                        <strong style=\"color: var(--cyber-neon-pink);\">Company:</strong><br>
                        <span style=\"color: rgba(255, 255, 255, 0.8);\">\${submission.company}</span>
                    </div>
                    \` : ''}
                    
                    <div style=\"margin-bottom: 1rem;\">
                        <strong style=\"color: var(--cyber-neon-pink);\">Service:</strong>
                        <span style=\"color: var(--cyber-neon-green); margin-left: 0.5rem;\">\${submission.service}</span>
                    </div>
                    
                    \${submission.budget ? \`
                    <div style=\"margin-bottom: 1rem;\">
                        <strong style=\"color: var(--cyber-neon-pink);\">Budget:</strong>
                        <span style=\"color: var(--cyber-neon-orange); margin-left: 0.5rem;\">\${submission.budget}</span>
                    </div>
                    \` : ''}
                </div>
                
                <div style=\"margin-bottom: 1.5rem;\">
                    <strong style=\"color: var(--cyber-neon-pink);\">Message:</strong><br>
                    <p style=\"color: rgba(255, 255, 255, 0.8); line-height: 1.5; margin-top: 0.5rem;\">
                        \${submission.message}
                    </p>
                </div>
                
                <div style=\"display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: rgba(255, 255, 255, 0.6);\">
                    <span>
                        <i class=\"fas fa-clock\" style=\"margin-right: 0.5rem;\"></i>
                        \${new Date(submission.timestamp).toLocaleString()}
                    </span>
                    \${submission.updates ? 
                        '<span style=\"color: var(--cyber-neon-green);\"><i class=\"fas fa-bell\" style=\"margin-right: 0.5rem;\"></i>Wants Updates</span>' : 
                        '<span style=\"color: rgba(255, 255, 255, 0.4);\"><i class=\"fas fa-bell-slash\" style=\"margin-right: 0.5rem;\"></i>No Updates</span>'
                    }
                </div>
            </div>
        \`).join('');
    }
}

// Initialize admin panel
let adminPanel;

document.addEventListener('DOMContentLoaded', () => {
    adminPanel = new AdminPanel();
});

// Global refresh function
function refreshData() {
    if (adminPanel) {
        adminPanel.loadSubmissions();
    }
}
";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../utilities/head.php'; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="../index.php" class="navbar-brand">HIVENEST ADMIN</a>
            
            <ul class="navbar-nav">
                <li><a href="../index.php">HOME</a></li>
                <li><a href="admin.php" class="<?php echo ($current_page == 'admin') ? 'active' : ''; ?>">CONTACT SUBMISSIONS</a></li>
                <li><a href="#" onclick="refreshData()" class="btn btn-primary">REFRESH DATA</a></li>
            </ul>
        </div>
    </nav>

    <!-- Admin Panel -->
    <section class="section" style="padding-top: 8rem;">
        <div class="container">
            <div class="text-center mb-8">
                <h1>CONTACT FORM SUBMISSIONS</h1>
                <p class="hero-subtitle">Monitor incoming transmissions from potential clients</p>
            </div>
            
            <!-- Loading State -->
            <div id="loading" class="text-center" style="display: none;">
                <div class="cyber-card" style="padding: 3rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--cyber-neon-cyan); margin-bottom: 1rem;"></i>
                    <p style="color: var(--cyber-neon-cyan);">Loading transmissions...</p>
                </div>
            </div>

            <!-- Error State -->
            <div id="error" class="text-center" style="display: none;">
                <div class="cyber-card" style="padding: 3rem; border-color: var(--cyber-neon-pink);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--cyber-neon-pink); margin-bottom: 1rem;"></i>
                    <p style="color: var(--cyber-neon-pink);">Failed to load transmissions. Check neural network connection.</p>
                    <button onclick="loadSubmissions()" class="btn btn-primary" style="margin-top: 1rem;">RETRY CONNECTION</button>
                </div>
            </div>

            <!-- Data Container -->
            <div id="submissions-container" class="services-grid">
                <!-- Submissions will be populated by JavaScript -->
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="text-center" style="display: none;">
                <div class="cyber-card" style="padding: 3rem;">
                    <i class="fas fa-satellite-dish" style="font-size: 3rem; color: var(--cyber-neon-green); margin-bottom: 1rem;"></i>
                    <p style="color: var(--cyber-neon-green);">No transmissions received yet. The matrix awaits...</p>
                </div>
            </div>
        </div>
    </section>

<?php include '../utilities/footer.php'; ?>

<?php include '../utilities/scripts.php'; ?>
</body>
</html>