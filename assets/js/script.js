// SSLG Voting System JavaScript

// Candidate selection functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle candidate card clicks
    const candidateCards = document.querySelectorAll('.candidate-card');
    candidateCards.forEach(card => {
        card.addEventListener('click', function() {
            const position = this.dataset.position;
            const candidateId = this.dataset.candidate;

            // Remove selected class from other cards in same position
            document.querySelectorAll(`.candidate-card[data-position="${position}"]`).forEach(c => {
                c.classList.remove('selected');
            });

            // Add selected class to clicked card
            this.classList.add('selected');

            // Update radio button
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });

    // Submit vote button
    const submitVoteBtn = document.getElementById('submitVoteBtn');
    if (submitVoteBtn) {
        submitVoteBtn.addEventListener('click', function() {
            // Check if all positions have a selection
            const positions = document.querySelectorAll('.candidate-card');
            const selectedPositions = new Set();
            positions.forEach(card => {
                if (card.classList.contains('selected')) {
                    selectedPositions.add(card.dataset.position);
                }
            });

            const totalPositions = new Set(Array.from(positions).map(card => card.dataset.position)).size;

            if (selectedPositions.size < totalPositions) {
                alert('Please select a candidate for each position before submitting your vote.');
                return;
            }

            // Show confirmation modal
            showVoteConfirmation();
        });
    }

    // Confirm vote button
    const confirmVoteBtn = document.getElementById('confirmVoteBtn');
    if (confirmVoteBtn) {
        confirmVoteBtn.addEventListener('click', function() {
            document.getElementById('voteForm').submit();
        });
    }
});

// Show vote confirmation modal
function showVoteConfirmation() {
    const selectedCandidates = document.querySelectorAll('.candidate-card.selected');
    let summary = '<h6>Your Selections:</h6><ul class="list-group list-group-flush">';

    selectedCandidates.forEach(card => {
        const position = card.dataset.position;
        const candidateName = card.querySelector('h5').textContent;
        summary += `<li class="list-group-item">${position}: ${candidateName}</li>`;
    });

    summary += '</ul><p class="mt-3 text-muted">Click "Confirm Vote" to submit your selections. You cannot change your vote after submission.</p>';

    document.getElementById('voteSummary').innerHTML = summary;
    const modal = new bootstrap.Modal(document.getElementById('confirmVoteModal'));
    modal.show();
}

// Auto-refresh results (for admin dashboard and results page)
function autoRefreshResults() {
    if (document.getElementById('resultsContainer')) {
        setInterval(function() {
            location.reload();
        }, 30000); // Refresh every 30 seconds
    }
}

// Print vote receipt
function printReceipt() {
    window.print();
}

// CSRF token refresh (if needed)
function refreshCSRFToken() {
    fetch('get_csrf_token.php')
        .then(response => response.json())
        .then(data => {
            const tokenInputs = document.querySelectorAll('input[name="csrf_token"]');
            tokenInputs.forEach(input => {
                input.value = data.token;
            });
        })
        .catch(error => console.error('Error refreshing CSRF token:', error));
}

// Session timeout warning
let sessionWarningShown = false;
function checkSessionTimeout() {
    // This would be called periodically to check session status
    // For now, just a placeholder
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    autoRefreshResults();
    checkSessionTimeout();
});
