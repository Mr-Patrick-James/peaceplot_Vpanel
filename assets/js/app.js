(function(){
  const path = (location.pathname || "").split("/").pop() || "index.html";
  const links = document.querySelectorAll(".nav a");
  links.forEach(a => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if ((href || "") === path) a.classList.add("active");
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if(!btn) return;
    const action = btn.getAttribute("data-action");
    const lot = btn.getAttribute("data-lot") || "";

    /* 
    Generic listeners removed - individual pages (cemetery-lots.js, burial-records.js) 
    now handle their own specific actions.
    */
  });

  // Universal Global Search Logic
  const searchInput = document.getElementById('universalSearch');
  const searchResults = document.getElementById('searchResults');
  let searchTimeout = null;

  // Check if there's a search query in the URL to persist it in the box
  const urlParams = new URLSearchParams(window.location.search);
  const existingQuery = urlParams.get('q');
  if (existingQuery && searchInput) {
    searchInput.value = existingQuery;
  }

  if (searchInput && searchResults) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = searchInput.value.trim();
        if (query.length >= 2) {
          window.location.href = `search-results.php?q=${encodeURIComponent(query)}`;
        }
      }
    });

    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      clearTimeout(searchTimeout);

      if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await fetch(`../api/universal_search.php?q=${encodeURIComponent(query)}`);
          const result = await response.json();

          if (result.success && result.data && result.data.length > 0) {
            let resultsHtml = result.data.map(item => `
              <a href="${item.url}" class="search-result-item">
                <div class="result-icon icon-${item.type}">
                  ${item.type === 'lot' ? 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' : 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                  }
                </div>
                <div class="result-info">
                  <span class="result-title">${item.title}</span>
                  <span class="result-subtitle">${item.subtitle}</span>
                </div>
              </a>
            `).join('');
            
            // Add a "View all results" option at the bottom
            resultsHtml += `
              <a href="search-results.php?q=${encodeURIComponent(query)}" style="display: block; padding: 12px; text-align: center; background: #f8fafc; color: #3b82f6; font-size: 13px; font-weight: 600; text-decoration: none; border-top: 1px solid #f1f5f9;">
                View all results for "${query}"
              </a>
            `;
            
            searchResults.innerHTML = resultsHtml;
            searchResults.style.display = 'block';
          } else {
            searchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 13px;">No results found</div>';
            searchResults.style.display = 'block';
          }
        } catch (error) {
          console.error('Search error:', error);
        }
      }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
      }
    });
  }
})();
