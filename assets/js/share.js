/**
 * share.js — Share buttons & copy-link functionality
 * Site: https://news.evaulthub.com
 *
 * Social share URLs are built server-side (PHP).
 * This file handles only the client-side copy-link button.
 */
(function () {
  'use strict';

  /**
   * copyLink()
   * Called from the "Copy Link" share button: onclick="copyLink(this)"
   *
   * @param {HTMLElement} btn  - The button element that was clicked
   */
  function copyLink(btn) {
    var url = window.location.href;
    var originalText = btn.textContent || btn.innerText;

    // Modern Clipboard API (preferred)
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        showCopied(btn, originalText);
      }).catch(function () {
        fallbackCopy(url, btn, originalText);
      });
    } else {
      fallbackCopy(url, btn, originalText);
    }
  }

  /**
   * Fallback for browsers that don't support navigator.clipboard
   */
  function fallbackCopy(text, btn, originalText) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';   // prevent page scroll
    textarea.style.opacity  = '0';
    textarea.style.left     = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
      document.execCommand('copy');
      showCopied(btn, originalText);
    } catch (err) {
      console.warn('Copy failed:', err);
    }

    document.body.removeChild(textarea);
  }

  /**
   * Temporarily change button text to "Copied!" then revert.
   */
  function showCopied(btn, originalText) {
    btn.textContent = 'Copied!';
    btn.setAttribute('aria-label', 'Link copied!');

    setTimeout(function () {
      btn.textContent = originalText;
      btn.setAttribute('aria-label', 'Copy link');
    }, 2000);
  }

  // Expose to global scope so inline onclick="copyLink(this)" works
  window.copyLink = copyLink;

})();
