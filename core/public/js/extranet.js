// Argus Hotel Operations Extranet Connectors

(function($) {
    'use strict';

    // 1. Live Clock
    function updateClock() {
        const now = new Date();
        const istOffset = 5.5 * 60 * 60 * 1000;
        const istDate = new Date(now.getTime() + (now.getTimezoneOffset() * 60 * 1000) + istOffset);
        const hours = String(istDate.getHours()).padStart(2, '0');
        const minutes = String(istDate.getMinutes()).padStart(2, '0');
        $('#liveClock').text('IST ' + hours + ':' + minutes);
    }
    // Initialize immediately
    updateClock();
    setInterval(updateClock, 10000);

    // 2. Poll Sync Status every 5 seconds (Section 9)
    function pollSyncStatus() {
        $.getJSON('/extranet/sync-status')
            .done(function(data) {
                if (data.channels) {
                    let allHealthy = true;
                    let activeCount = 0;
                    data.channels.forEach(function(ch) {
                        if (ch.status === 'active' && !ch.circuit_open) {
                            activeCount++;
                        } else {
                            allHealthy = false;
                        }
                    });

                    if (allHealthy) {
                        $('#sidebarHealthDot').removeClass('bg-danger bg-warning').addClass('bg-[#4C7168]');
                        $('#sidebarHealthText').text('All channels healthy (' + activeCount + '/' + data.channels.length + ' connected)');
                    } else {
                        $('#sidebarHealthDot').removeClass('bg-[#4C7168]').addClass('bg-warning');
                        $('#sidebarHealthText').text('Channel degraded (' + activeCount + '/' + data.channels.length + ' active)');
                    }
                }

                if (data.open_alerts_count !== undefined) {
                    const badge = $('#sidebarAlertBadge');
                    if (data.open_alerts_count > 0) {
                        if (badge.length) {
                            badge.text(data.open_alerts_count).show();
                        }
                    } else if (badge.length) {
                        badge.hide();
                    }
                }
            })
            .fail(function() {
                // Silently ignore network polling glitch
            });
    }

    if (window.location.pathname.startsWith('/extranet') || window.location.pathname === '/') {
        pollSyncStatus();
        setInterval(pollSyncStatus, 5000);
    }

    // 3. Close Hotel Selector Dropdown on Outside Click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#hotelSelectorContainer').length) {
            $('#hotelDropdownMenu').addClass('hidden');
            $('#hotelDropdownArrow').removeClass('rotate-180');
        }
    });
})(jQuery);
