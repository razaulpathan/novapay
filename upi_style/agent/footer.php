    </main> <!-- Closes content-body -->
  </div> <!-- Closes main-container -->

  <!-- Drawer Slider: Settings Panel -->
  <div class="settings-drawer" id="settings-drawer">
    <div class="drawer-header">
      <span class="drawer-title">Layout Settings</span>
      <button class="drawer-close-btn" onclick="toggleSettingsDrawer()">&times;</button>
    </div>
    
    <div class="drawer-body">
      <!-- Theme Selection Section -->
      <div class="drawer-section">
        <div class="drawer-section-title">Theme Colors</div>
        <div class="theme-color-grid">
          <div class="theme-color-dot" style="background-color: #00b578;" onclick="changeThemeColor('#00b578')" data-color="#00b578" title="Green"></div>
          <div class="theme-color-dot" style="background-color: #1c82e6;" onclick="changeThemeColor('#1c82e6')" data-color="#1c82e6" title="Blue"></div>
          <div class="theme-color-dot" style="background-color: #ff2d55;" onclick="changeThemeColor('#ff2d55')" data-color="#ff2d55" title="Magenta"></div>
          <div class="theme-color-dot" style="background-color: #fa8c16;" onclick="changeThemeColor('#fa8c16')" data-color="#fa8c16" title="Orange"></div>
          <div class="theme-color-dot" style="background-color: #722ed1;" onclick="changeThemeColor('#722ed1')" data-color="#722ed1" title="Purple"></div>
          <div class="theme-color-dot" style="background-color: #13c2c2;" onclick="changeThemeColor('#13c2c2')" data-color="#13c2c2" title="Teal"></div>
          <div class="theme-color-dot" style="background-color: #faad14;" onclick="changeThemeColor('#faad14')" data-color="#faad14" title="Yellow"></div>
          <div class="theme-color-dot" style="background-color: #1890ff;" onclick="changeThemeColor('#1890ff')" data-color="#1890ff" title="Day Blue"></div>
        </div>
      </div>

      <!-- Interface Configurations Section -->
      <div class="drawer-section">
        <div class="drawer-section-title">Interface Settings</div>
        
        <div class="setting-toggle-row">
          <span class="setting-toggle-label">Dark Mode</span>
          <label class="switch-control">
            <input type="checkbox" id="toggle-dark-mode" onchange="toggleDarkModeSetting(this.checked)">
            <span class="switch-slider"></span>
          </label>
        </div>

        <div class="setting-toggle-row">
          <span class="setting-toggle-label">Enable Tabs</span>
          <label class="switch-control">
            <input type="checkbox" id="toggle-tabbar" onchange="toggleTabbarSetting(this.checked)">
            <span class="switch-slider"></span>
          </label>
        </div>

        <div class="setting-toggle-row">
          <span class="setting-toggle-label">Enable Breadcrumbs</span>
          <label class="switch-control">
            <input type="checkbox" id="toggle-breadcrumbs" onchange="toggleBreadcrumbsSetting(this.checked)">
            <span class="switch-slider"></span>
          </label>
        </div>

        <div class="setting-toggle-row">
          <span class="setting-toggle-label">Enable Logo</span>
          <label class="switch-control">
            <input type="checkbox" id="toggle-logo" onchange="toggleLogoSetting(this.checked)">
            <span class="switch-slider"></span>
          </label>
        </div>
      </div>

      <div class="drawer-tip-box">
        After settings, it will save in your local cache immediately. Click copy to capture the preferences script.
      </div>
      
      <button class="drawer-btn btn-primary-theme" onclick="copyDrawerConfig()">Copy Config</button>
      <button class="drawer-btn btn-secondary-theme" onclick="resetDrawerConfig()" style="margin-top: 8px;">Reset Preferences</button>
    </div>
  </div>

  <!-- Settings Drawer Overlay backdrop -->
  <div class="drawer-overlay" id="drawer-overlay" onclick="toggleSettingsDrawer()"></div>

  <!-- Global Toast Alert Container -->
  <div id="global-toast" class="global-toast-message"></div>

  <!-- Settings Drawer CSS Styles -->
  <style>
    .settings-drawer {
      position: fixed;
      top: 0;
      right: -300px;
      width: 300px;
      height: 100vh;
      background: var(--bg-sidebar-secondary);
      border-left: 1px solid var(--border-color);
      box-shadow: -4px 0 16px rgba(0, 0, 0, 0.08);
      z-index: 1010;
      display: flex;
      flex-direction: column;
      transition: right 0.3s cubic-bezier(0.7, 0.3, 0.1, 1);
    }

    .settings-drawer.open {
      right: 0;
    }

    .drawer-header {
      padding: 18px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .drawer-title {
      font-size: 15px;
      font-weight: 800;
      color: var(--text-main);
    }

    .drawer-close-btn {
      background: transparent;
      border: 0;
      font-size: 24px;
      color: var(--text-secondary);
      cursor: pointer;
      line-height: 1;
    }

    .drawer-body {
      padding: 18px;
      overflow-y: auto;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .drawer-section {
      margin-bottom: 24px;
    }

    .drawer-section-title {
      font-size: 12.5px;
      font-weight: 800;
      color: var(--text-secondary);
      text-transform: uppercase;
      margin-bottom: 14px;
      letter-spacing: 0.5px;
    }

    .theme-color-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
    }

    .theme-color-dot {
      height: 28px;
      border-radius: 6px;
      cursor: pointer;
      position: relative;
      transition: transform 0.15s;
      border: 2px solid transparent;
    }

    .theme-color-dot:hover {
      transform: scale(1.1);
    }

    .theme-color-dot.selected::after {
      content: '✓';
      position: absolute;
      color: white;
      font-weight: bold;
      font-size: 12px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .setting-toggle-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
    }

    .setting-toggle-label {
      font-size: 13.5px;
      font-weight: 600;
      color: var(--text-main);
    }

    /* Switch styling */
    .switch-control {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 22px;
    }

    .switch-control input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .switch-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: var(--border-color);
      transition: .2s;
      border-radius: 34px;
    }

    .switch-slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .2s;
      border-radius: 50%;
    }

    input:checked + .switch-slider {
      background-color: var(--primary);
    }

    input:checked + .switch-slider:before {
      transform: translateX(22px);
    }

    .drawer-tip-box {
      font-size: 12px;
      background: rgba(var(--primary-rgb), 0.05);
      border: 1px solid rgba(var(--primary-rgb), 0.15);
      color: var(--primary);
      padding: 12px;
      border-radius: 8px;
      line-height: 1.4;
      margin-top: auto;
      margin-bottom: 16px;
    }

    .drawer-btn {
      width: 100%;
      height: 38px;
      border: 0;
      border-radius: 6px;
      font-size: 13.5px;
      font-weight: 700;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .drawer-btn:hover {
      opacity: 0.9;
    }

    .btn-primary-theme {
      background: var(--primary);
      color: white;
    }

    .btn-secondary-theme {
      background: var(--table-header-bg);
      color: var(--text-main);
      border: 1px solid var(--border-color);
    }

    .drawer-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.25);
      z-index: 1000;
      display: none;
    }

    .drawer-overlay.open {
      display: block;
    }

    /* Global Toast Alert styles */
    .global-toast-message {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #1e293b;
      color: #ffffff;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 600;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      display: none;
      z-index: 2000;
      animation: toastFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes toastFadeIn {
      from { opacity: 0; transform: translateY(12px) scale(0.95); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
  </style>

  <!-- Global Javascript implementations -->
  <script>
    // Tab List Definitions
    const TAB_INFO = {
      'index.php': { title: 'Home', file: 'index.php' },
      'team_daily.php': { title: 'Team Daily', file: 'team_daily.php' },
      'token_stmt.php': { title: 'Token STMT', file: 'token_stmt.php' },
      'token_stmt_his.php': { title: 'Token STMT HIS', file: 'token_stmt_his.php' },
      'daily_stat.php': { title: 'Daily Statistic', file: 'daily_stat.php' },
      'sub_agent.php': { title: 'Register Sub-Agent', file: 'sub_agent.php' },
      'transfer_token.php': { title: 'Transfer Token', file: 'transfer_token.php' },
      'security.php': { title: 'Security (2FA)', file: 'security.php' },
      'support_tickets.php': { title: 'Support Desk', file: 'support_tickets.php' },
      'security_logs.php': { title: 'Security Logs', file: 'security_logs.php' },
      'leaderboard.php': { title: 'Leaderboard', file: 'leaderboard.php' }
    };

    const ACTIVE_FILE = '<?php echo $current_page; ?>';

    // Toggle Settings drawer display
    function toggleSettingsDrawer() {
      const drawer = document.getElementById('settings-drawer');
      const overlay = document.getElementById('drawer-overlay');
      drawer.classList.toggle('open');
      overlay.classList.toggle('open');
    }

    // Toggle sidebar collapse state
    function toggleSidebarCollapse() {
      const sidebar = document.getElementById('sidebar-container');
      sidebar.classList.toggle('collapsed');
      
      const isCollapsed = sidebar.classList.contains('collapsed');
      localStorage.setItem('agent-sidebar-collapsed', isCollapsed);
    }

    // Global Toast Messages
    function showGlobalToast(msg) {
      const toast = document.getElementById('global-toast');
      toast.innerText = msg;
      toast.style.display = 'block';
      setTimeout(() => {
        toast.style.display = 'none';
      }, 3000);
    }

    // Theme Color controls
    function changeThemeColor(color) {
      localStorage.setItem('agent-theme-color', color);
      document.documentElement.style.setProperty('--primary', color);
      
      const hexToRgb = hex => {
        let c = hex.substring(1);
        let rgb = parseInt(c, 16);
        let r = (rgb >> 16) & 0xff;
        let g = (rgb >> 8) & 0xff;
        let b = rgb & 0xff;
        return `${r}, ${g}, ${b}`;
      };
      document.documentElement.style.setProperty('--primary-rgb', hexToRgb(color));
      
      updateSelectedColorDot(color);
      showGlobalToast(`Theme updated successfully!`);
    }

    function updateSelectedColorDot(color) {
      document.querySelectorAll('.theme-color-dot').forEach(dot => {
        if (dot.getAttribute('data-color') === color) {
          dot.classList.add('selected');
        } else {
          dot.classList.remove('selected');
        }
      });
    }

    // Interface Toggle handlers
    function toggleDarkModeSetting(checked) {
      localStorage.setItem('agent-enable-dark', checked);
      if (checked) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      showGlobalToast(checked ? "Dark Mode activated!" : "Light Mode activated!");
    }

    function toggleTabbarSetting(checked) {
      localStorage.setItem('agent-enable-tabs', checked);
      applyConfigStyle('tabbar-container', checked ? 'flex' : 'none');
    }

    function toggleBreadcrumbsSetting(checked) {
      localStorage.setItem('agent-enable-breadcrumbs', checked);
      applyConfigStyle('breadcrumbs-bar', checked ? 'flex' : 'none');
    }

    function toggleLogoSetting(checked) {
      localStorage.setItem('agent-enable-logo', checked);
      applyConfigStyle('sidebar-brand-wrapper', checked ? 'flex' : 'none');
    }

    function applyConfigStyle(className, displayVal) {
      let styleEl = document.getElementById('theme-setting-styles');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'theme-setting-styles';
        document.head.appendChild(styleEl);
      }
      
      const enableBreadcrumbs = localStorage.getItem('agent-enable-breadcrumbs') !== 'false';
      const enableTabs = localStorage.getItem('agent-enable-tabs') !== 'false';
      const enableLogo = localStorage.getItem('agent-enable-logo') !== 'false';

      styleEl.innerHTML = `
        .breadcrumbs-bar { display: ${enableBreadcrumbs ? 'flex' : 'none'} !important; }
        .tabbar-container { display: ${enableTabs ? 'flex' : 'none'} !important; }
        .sidebar-brand-wrapper { display: ${enableLogo ? 'flex' : 'none'} !important; }
      `;
    }

    // Copy / Reset Configurations
    function copyDrawerConfig() {
      const config = {
        themeColor: localStorage.getItem('agent-theme-color') || '#00b578',
        darkMode: localStorage.getItem('agent-enable-dark') === 'true',
        enableTabs: localStorage.getItem('agent-enable-tabs') !== 'false',
        enableBreadcrumbs: localStorage.getItem('agent-enable-breadcrumbs') !== 'false',
        enableLogo: localStorage.getItem('agent-enable-logo') !== 'false'
      };

      const copyStr = `localStorage.setItem('agent-theme-color', '${config.themeColor}');\n` + 
                      `localStorage.setItem('agent-enable-dark', ${config.darkMode});\n` +
                      `localStorage.setItem('agent-enable-tabs', ${config.enableTabs});\n` +
                      `localStorage.setItem('agent-enable-breadcrumbs', ${config.enableBreadcrumbs});\n` +
                      `localStorage.setItem('agent-enable-logo', ${config.enableLogo});`;

      navigator.clipboard.writeText(copyStr).then(() => {
        showGlobalToast("Config commands copied to clipboard!");
      });
    }

    function resetDrawerConfig() {
      localStorage.removeItem('agent-theme-color');
      localStorage.removeItem('agent-enable-dark');
      localStorage.removeItem('agent-enable-tabs');
      localStorage.removeItem('agent-enable-breadcrumbs');
      localStorage.removeItem('agent-enable-logo');
      
      showGlobalToast("Preferences cleared. Reloading page...");
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    }

    // --- Dynamic Multi-Tab System (persistent across reload) ---
    function initializeTabsSystem() {
      let openTabs = [];
      try {
        openTabs = JSON.parse(localStorage.getItem('agent-open-tabs')) || [];
      } catch (e) {
        openTabs = [];
      }

      // Format open tabs safely
      if (!Array.isArray(openTabs) || openTabs.length === 0) {
        openTabs = [{ title: 'Home', file: 'index.php' }];
      }

      // Verify that the active page exists in the open tabs
      const activeTabIdx = openTabs.findIndex(t => t.file === ACTIVE_FILE);
      if (activeTabIdx === -1 && TAB_INFO[ACTIVE_FILE]) {
        openTabs.push(TAB_INFO[ACTIVE_FILE]);
      }

      localStorage.setItem('agent-open-tabs', JSON.stringify(openTabs));
      renderTabsMarkup(openTabs);
    }

    function renderTabsMarkup(tabs) {
      const container = document.getElementById('tabbar-container');
      if (!container) return;

      container.innerHTML = '';
      
      tabs.forEach(tab => {
        const tabEl = document.createElement('a');
        tabEl.href = tab.file;
        tabEl.className = `workspace-tab ${tab.file === ACTIVE_FILE ? 'active' : ''}`;
        
        // Active indicator dot
        if (tab.file === ACTIVE_FILE) {
          const dot = document.createElement('span');
          dot.className = 'workspace-tab-dot';
          tabEl.appendChild(dot);
        }

        const label = document.createElement('span');
        label.innerText = tab.title;
        tabEl.appendChild(label);

        // Close button: Home tab cannot be closed (primary dashboard anchor)
        if (tab.file !== 'index.php') {
          const closeBtn = document.createElement('span');
          closeBtn.className = 'workspace-tab-close';
          closeBtn.innerHTML = '&times;';
          closeBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeTabAction(tab.file);
          };
          tabEl.appendChild(closeBtn);
        }

        container.appendChild(tabEl);
      });
    }

    function closeTabAction(file) {
      let openTabs = JSON.parse(localStorage.getItem('agent-open-tabs')) || [];
      openTabs = openTabs.filter(t => t.file !== file);
      
      localStorage.setItem('agent-open-tabs', JSON.stringify(openTabs));

      // If we closed the active tab, redirect browser to the last open tab
      if (file === ACTIVE_FILE) {
        const redirectPage = openTabs[openTabs.length - 1].file;
        window.location.href = redirectPage;
      } else {
        renderTabsMarkup(openTabs);
      }
    }

    // Page Load Initializations
    document.addEventListener("DOMContentLoaded", () => {
      // Toggle checkboxes status inside settings drawer
      document.getElementById('toggle-dark-mode').checked = localStorage.getItem('agent-enable-dark') === 'true';
      document.getElementById('toggle-tabbar').checked = localStorage.getItem('agent-enable-tabs') !== 'false';
      document.getElementById('toggle-breadcrumbs').checked = localStorage.getItem('agent-enable-breadcrumbs') !== 'false';
      document.getElementById('toggle-logo').checked = localStorage.getItem('agent-enable-logo') !== 'false';

      // Set sidebar collapse state
      const isCollapsed = localStorage.getItem('agent-sidebar-collapsed') === 'true';
      const sidebar = document.getElementById('sidebar-container');
      if (isCollapsed && sidebar) {
        sidebar.classList.add('collapsed');
      }

      // Highlight selected theme color circle
      const themeColor = localStorage.getItem('agent-theme-color') || '#00b578';
      updateSelectedColorDot(themeColor);

      // Run Tab Engine
      initializeTabsSystem();
    });
  </script>
</body>
</html>

