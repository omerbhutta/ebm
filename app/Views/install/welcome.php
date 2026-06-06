<div class="install-card">
    <div class="install-hero">
        <div class="hero-emoji">📧</div>
        <h1>Welcome to Email Bounce Monitor</h1>
        <p class="hero-tagline">A professional way to track undeliverable emails (NDRs) across your Microsoft 365 mailboxes.</p>
    </div>

    <div class="hero-feature-grid">
        <div class="feature">
            <div class="feature-ico">🔌</div>
            <h3>Graph API</h3>
            <p>Connects directly to Microsoft 365 using OAuth client credentials.</p>
        </div>
        <div class="feature">
            <div class="feature-ico">🗂️</div>
            <h3>Suppression List</h3>
            <p>Cross-check any address before sending to avoid sending to known bounces.</p>
        </div>
        <div class="feature">
            <div class="feature-ico">📨</div>
            <h3>Multi-Mailbox</h3>
            <p>Monitor an unlimited number of mailboxes from a single dashboard.</p>
        </div>
        <div class="feature">
            <div class="feature-ico">🔐</div>
            <h3>Two-Tier Access</h3>
            <p>Separate passwords for viewers and administrators.</p>
        </div>
    </div>

    <div class="install-checklist">
        <h3>Before you begin</h3>
        <ul>
            <li>A MySQL/MariaDB database (you'll need host, name, user, password)</li>
            <li>An Azure App Registration with <code>Mail.Read</code> application permission</li>
            <li>The Tenant ID, Client ID, and Client Secret from that App Registration</li>
            <li>Two strong passwords (one for viewers, one for admin)</li>
        </ul>
    </div>

    <div class="install-actions">
        <a class="btn btn-primary btn-lg" href="<?= \App\Core\View::url('/install/requirements') ?>">
            Begin installation →
        </a>
    </div>
</div>
