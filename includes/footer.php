    </div><!-- /.content -->
  </main>
</div><!-- /.layout -->

<dialog id="confirmDialog" class="confirm-dialog" aria-labelledby="confirmTitle" aria-describedby="confirmMsg">
  <div class="confirm-icon" aria-hidden="true"><?= icon('alert', 22) ?></div>
  <h2 id="confirmTitle" data-confirm-title>Konfirmasi</h2>
  <p id="confirmMsg" data-confirm-msg></p>
  <div class="confirm-actions">
    <button type="button" class="btn btn-secondary" data-confirm-no>Batal</button>
    <button type="button" class="btn btn-danger-solid" data-confirm-yes>Lanjutkan</button>
  </div>
</dialog>

<script src="<?= BASE_URL ?>/assets/script.js"></script>
</body>
</html>
