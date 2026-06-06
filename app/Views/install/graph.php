<?php /** @var array $data; @var array $errors */ ?>
<div class="install-card">
    <header class="install-card-head">
        <h1>Microsoft Graph API</h1>
        <p>Connect to Microsoft 365 using an Azure App Registration with <code>Mail.Read</code> permission.</p>
    </header>

    <details class="install-hint">
        <summary>How do I get these values?</summary>
        <ol>
            <li>Go to <a href="https://portal.azure.com" target="_blank" rel="noopener">portal.azure.com</a> → <strong>Microsoft Entra ID</strong> → <strong>App registrations</strong> → <strong>New registration</strong>.</li>
            <li>Give it a name (e.g. "EBM Bounce Monitor"), choose <em>Single tenant</em>, and register.</li>
            <li>Copy the <strong>Application (client) ID</strong> and <strong>Directory (tenant) ID</strong> from the Overview page.</li>
            <li>Go to <strong>Certificates &amp; secrets</strong> → <strong>New client secret</strong>, copy the <em>Value</em> (not the ID).</li>
            <li>Under <strong>API permissions</strong>, add <em>Microsoft Graph → Application → Mail.Read</em> and click <em>Grant admin consent</em>.</li>
        </ol>
    </details>

    <?php if (!empty($errors)): ?>
        <div class="install-alert install-alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="install-form">
        <div class="form-field">
            <label for="tenant_id">Tenant ID (Directory ID)</label>
            <input id="tenant_id" name="tenant_id" type="text" value="<?= htmlspecialchars($data['tenant_id']) ?>"
                   placeholder="00000000-0000-0000-0000-000000000000" required pattern="[0-9a-fA-F\-]{36}">
        </div>

        <div class="form-field">
            <label for="client_id">Client ID (Application ID)</label>
            <input id="client_id" name="client_id" type="text" value="<?= htmlspecialchars($data['client_id']) ?>"
                   placeholder="00000000-0000-0000-0000-000000000000" required pattern="[0-9a-fA-F\-]{36}">
        </div>

        <div class="form-field">
            <label for="client_secret">Client Secret</label>
            <input id="client_secret" name="client_secret" type="password" value="<?= htmlspecialchars($data['client_secret']) ?>"
                   placeholder="The Value (not the ID) from the secret you generated" required autocomplete="off">
            <small>This is the secret value shown only once when you created it in Azure.</small>
        </div>

        <div class="install-actions">
            <a class="btn btn-ghost" href="<?= \App\Core\View::url('/install/database') ?>">← Back</a>
            <button class="btn btn-secondary" type="submit" name="action" value="test">Test Graph Connection</button>
            <button class="btn btn-primary" type="submit" name="action" value="save">Save &amp; Continue →</button>
        </div>
    </form>
</div>
