    }
    
    // Simpan User ID untuk Biometrik Login di PWA
    <?php if(isset($_GET['login_success'])): ?>
        localStorage.setItem('laundry_user_id', '<?php echo $_SESSION['user_id']; ?>');
    <?php endif; ?>
    </script>
</body>
</html>
