const dots = document.querySelectorAll('.city-dot');
const nameEl = document.getElementById('cityName');
const populationEl = document.getElementById('population');
const input = document.getElementById('cityInput');
const start = document.getElementById('startButton');

dots.forEach(dot => {
  dot.addEventListener('click', () => {
    dots.forEach(d => d.classList.remove('selected'));
    dot.classList.add('selected');
    nameEl.textContent = dot.dataset.city;
    populationEl.textContent = Number(dot.dataset.pop).toLocaleString('lt-LT');
    input.value = dot.dataset.city;
    start.disabled = false;
  });
});
