document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('actionBtn');
    if (btn) {
        btn.addEventListener('click', () => {
            alert('JavaScript opérationnel sans bundler !');
        });
    }
});