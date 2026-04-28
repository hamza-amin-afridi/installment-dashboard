// MotoLease Pro — Chart.js Initialization
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined' || !window.CHART_DATA) return;

  const isDark = () => document.documentElement.getAttribute('data-theme') !== 'light';
  const gridColor  = () => isDark() ? 'rgba(46,51,83,0.8)' : 'rgba(200,205,230,0.8)';
  const textColor  = () => isDark() ? '#8892b0' : '#5a6480';
  const accent     = '#6c63ff';
  const success    = '#2ed573';
  const warning    = '#ffa502';
  const danger     = '#ff4757';

  const defaultFontFamily = "'Poppins', sans-serif";
  Chart.defaults.font.family = defaultFontFamily;

  // ===== Income Bar Chart =====
  const incomeCtx = document.getElementById('incomeChart');
  if (incomeCtx && CHART_DATA.income.labels.length) {
    new Chart(incomeCtx, {
      type: 'bar',
      data: {
        labels: CHART_DATA.income.labels,
        datasets: [{
          label: 'Revenue (₨)',
          data: CHART_DATA.income.values,
          backgroundColor: 'rgba(108,99,255,0.7)',
          borderColor: accent,
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: gridColor() }, ticks: { color: textColor() } },
          y: { grid: { color: gridColor() }, ticks: { color: textColor(), callback: v => '₨' + v.toLocaleString() } }
        }
      }
    });
  } else if (incomeCtx) {
    incomeCtx.parentElement.innerHTML += '<p style="text-align:center;color:var(--text-faint);padding:32px">No income data yet.</p>';
    incomeCtx.remove();
  }

  // ===== Customer Growth Line Chart =====
  const growthCtx = document.getElementById('growthChart');
  if (growthCtx && CHART_DATA.growth.labels.length) {
    new Chart(growthCtx, {
      type: 'line',
      data: {
        labels: CHART_DATA.growth.labels,
        datasets: [{
          label: 'New Customers',
          data: CHART_DATA.growth.values,
          borderColor: success,
          backgroundColor: 'rgba(46,213,115,0.12)',
          borderWidth: 2.5,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: success,
          pointRadius: 5,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: gridColor() }, ticks: { color: textColor() } },
          y: { grid: { color: gridColor() }, ticks: { color: textColor(), stepSize: 1 } }
        }
      }
    });
  } else if (growthCtx) {
    growthCtx.parentElement.innerHTML += '<p style="text-align:center;color:var(--text-faint);padding:32px">No customer data yet.</p>';
    growthCtx.remove();
  }

  // ===== Plan Distribution Doughnut =====
  const planCtx = document.getElementById('planChart');
  if (planCtx && CHART_DATA.plans.labels.length) {
    new Chart(planCtx, {
      type: 'doughnut',
      data: {
        labels: CHART_DATA.plans.labels,
        datasets: [{
          data: CHART_DATA.plans.values,
          backgroundColor: [accent, success, warning, danger, '#1e90ff', '#a29bfe'],
          borderColor: isDark() ? '#21253a' : '#ffffff',
          borderWidth: 3,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        cutout: '65%',
        plugins: {
          legend: { position: 'bottom', labels: { color: textColor(), padding: 16, font: { size: 12 } } }
        }
      }
    });
  } else if (planCtx) {
    planCtx.parentElement.innerHTML += '<p style="text-align:center;color:var(--text-faint);padding:32px">No plan data yet.</p>';
    planCtx.remove();
  }
});
