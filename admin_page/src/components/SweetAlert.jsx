import React, { useEffect } from 'react';
import './SweetAlert.css';

const SweetAlert = ({ 
  isOpen, 
  onClose, 
  type = 'success', 
  title, 
  message, 
  showConfirmButton = true,
  showCancelButton = false,
  confirmText = 'ตกลง',
  cancelText = 'ยกเลิก',
  onConfirm,
  onCancel,
  autoClose = true,
  autoCloseTime = 3000
}) => {
  
  useEffect(() => {
    // จัดการ body scroll เมื่อเปิด/ปิด alert
    if (isOpen) {
      document.body.classList.add('sweet-alert-open');
      // ป้องกันการเลื่อนด้วย preventDefault
      const preventDefault = (e) => e.preventDefault();
      document.addEventListener('wheel', preventDefault, { passive: false });
      document.addEventListener('touchmove', preventDefault, { passive: false });
      
      return () => {
        document.body.classList.remove('sweet-alert-open');
        document.removeEventListener('wheel', preventDefault);
        document.removeEventListener('touchmove', preventDefault);
      };
    } else {
      document.body.classList.remove('sweet-alert-open');
    }
  }, [isOpen]);
  
  useEffect(() => {
    if (isOpen && autoClose && type !== 'confirm') {
      const timer = setTimeout(() => {
        onClose();
      }, autoCloseTime);
      
      return () => clearTimeout(timer);
    }
  }, [isOpen, autoClose, autoCloseTime, onClose, type]);

  if (!isOpen) return null;

  const getIcon = () => {
    switch (type) {
      case 'success':
        return (
          <div className="sweet-alert-icon success">
            <div className="sweet-alert-icon-content">
              <div className="sweet-alert-success-ring"></div>
              <div className="sweet-alert-success-checkmark">
                <div className="sweet-alert-success-line sweet-alert-success-line-1"></div>
                <div className="sweet-alert-success-line sweet-alert-success-line-2"></div>
              </div>
            </div>
          </div>
        );
      case 'error':
        return (
          <div className="sweet-alert-icon error">
            <div className="sweet-alert-icon-content">
              <div className="sweet-alert-error-x">
                <div className="sweet-alert-error-line sweet-alert-error-line-1"></div>
                <div className="sweet-alert-error-line sweet-alert-error-line-2"></div>
              </div>
            </div>
          </div>
        );
      case 'warning':
        return (
          <div className="sweet-alert-icon warning">
            <div className="sweet-alert-icon-content">
              <div className="sweet-alert-warning-body"></div>
              <div className="sweet-alert-warning-dot"></div>
            </div>
          </div>
        );
      case 'info':
        return (
          <div className="sweet-alert-icon info">
            <div className="sweet-alert-icon-content">
              <div className="sweet-alert-info-body"></div>
              <div className="sweet-alert-info-dot"></div>
            </div>
          </div>
        );
      case 'confirm':
        return (
          <div className="sweet-alert-icon confirm">
            <div className="sweet-alert-icon-content">
              <div className="sweet-alert-confirm-body">?</div>
            </div>
          </div>
        );
      default:
        return null;
    }
  };

  const handleConfirm = () => {
    if (onConfirm) {
      onConfirm();
    }
    onClose();
  };

  const handleCancel = () => {
    if (onCancel) {
      onCancel();
    }
    onClose();
  };

  const handleOverlayClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  return (
    <div className="sweet-alert-overlay" onClick={handleOverlayClick}>
      <div className="sweet-alert-container" onClick={(e) => e.stopPropagation()}>
        <div className="sweet-alert-content">
          {getIcon()}
          
          <div className="sweet-alert-text">
            <h2 className="sweet-alert-title">{title}</h2>
            {message && <p className="sweet-alert-message">{message}</p>}
          </div>
          
          <div className="sweet-alert-buttons">
            {showCancelButton && (
              <button 
                className="sweet-alert-button sweet-alert-button-cancel"
                onClick={handleCancel}
              >
                {cancelText}
              </button>
            )}
            {showConfirmButton && (
              <button 
                className={`sweet-alert-button sweet-alert-button-confirm ${type}`}
                onClick={handleConfirm}
              >
                {confirmText}
              </button>
            )}
          </div>
          
          {autoClose && type !== 'confirm' && (
            <div className="sweet-alert-progress">
              <div className="sweet-alert-progress-bar" style={{ animationDuration: `${autoCloseTime}ms` }}></div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default SweetAlert;
