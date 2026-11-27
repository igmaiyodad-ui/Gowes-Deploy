// ==========================================
// LOGIKA UNTUK MEMUNCULKAN KALENDER (DATE PICKER)
// ==========================================
function openDatePicker() {
  const input = document.getElementById('hiddenDateInput');
  if (input.showPicker) {
    input.showPicker();
  } else {
    input.focus();
    input.click();
  }
}