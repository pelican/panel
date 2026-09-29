// resources/js/observer.js
function observer() {
  return {
    observer: null,
    modalOpen: false,
    init: function() {
      const nodeSelector = ".fi-global-search-field";
      const node = document.querySelector(nodeSelector);
      if (node) {
        node.disabled = true;
        this.checkForTargetClass(node);
        this.listenForModalClose(node);
      }
    },
    checkForTargetClass: function(node) {
      const inputElement = node.querySelector("input[type=search]");
      if (inputElement) {
        ["focus", "click", "keydown", "input"].forEach((eventType) => {
          inputElement.addEventListener(eventType, (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.handleInputInteraction(event, node);
          }, true);
        });
        inputElement.addEventListener("keypress", (event) => {
          event.preventDefault();
          event.stopPropagation();
        }, true);
        inputElement.setAttribute("readonly", true);
        inputElement.setAttribute("tabindex", "-1");
      }
    },
    handleInputInteraction: function(event, node) {
      if (this.modalOpen) {
        return;
      }
      if (event.target) {
        event.target.blur();
      }
      this.openModal(node);
    },
    openModal: function(node) {
      this.modalOpen = true;
      node.disabled = true;
      window.dispatchEvent(new CustomEvent("open-global-search-modal", {
        detail: { id: "global-search-modal::plugin" },
        bubbles: true
      }));
    },
    listenForModalClose: function(node) {
      window.addEventListener("modal-closed", (event) => {
        if (event.detail?.id === "global-search-modal::plugin") {
          this.modalOpen = false;
          const inputElement = node.querySelector("input[type=search]");
          inputElement.disabled = false;
          inputElement.setAttribute("readonly", false);
          inputElement.setAttribute("tabindex", 0);
        }
      });
    }
  };
}
export {
  observer as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsiLi4vcmVzb3VyY2VzL2pzL29ic2VydmVyLmpzIl0sCiAgInNvdXJjZXNDb250ZW50IjogWyJleHBvcnQgZGVmYXVsdCBmdW5jdGlvbiBvYnNlcnZlcigpIHtcbiAgcmV0dXJuIHtcbiAgICBvYnNlcnZlcjogbnVsbCxcbiAgICBtb2RhbE9wZW46IGZhbHNlLFxuXG4gICAgaW5pdDogZnVuY3Rpb24gKCkge1xuICAgICAgY29uc3Qgbm9kZVNlbGVjdG9yID0gXCIuZmktZ2xvYmFsLXNlYXJjaC1maWVsZFwiO1xuICAgICAgY29uc3Qgbm9kZSA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3Iobm9kZVNlbGVjdG9yKTtcblxuICAgICAgaWYgKG5vZGUpIHtcbiAgICAgICAgbm9kZS5kaXNhYmxlZCA9IHRydWU7XG4gICAgICAgIHRoaXMuY2hlY2tGb3JUYXJnZXRDbGFzcyhub2RlKTtcbiAgICAgICAgdGhpcy5saXN0ZW5Gb3JNb2RhbENsb3NlKG5vZGUpO1xuICAgICAgfVxuICAgIH0sXG5cbiAgICBjaGVja0ZvclRhcmdldENsYXNzOiBmdW5jdGlvbiAobm9kZSkge1xuICAgICAgY29uc3QgaW5wdXRFbGVtZW50ID0gbm9kZS5xdWVyeVNlbGVjdG9yKFwiaW5wdXRbdHlwZT1zZWFyY2hdXCIpO1xuXG4gICAgICBpZiAoaW5wdXRFbGVtZW50KSB7XG4gICAgICAgIC8vIEV2ZW50cyB0aGF0IHNob3VsZCBvcGVuIHRoZSBtb2RhbCAoZGlkIG1vcmUgdGhhbiBmb2N1cyBhbmQgY2xpY2sgdG8gaGFuZGxlIGVkZ2UgY2FzZXMpXG4gICAgICAgIFtcImZvY3VzXCIsIFwiY2xpY2tcIiwgXCJrZXlkb3duXCIsIFwiaW5wdXRcIl0uZm9yRWFjaCgoZXZlbnRUeXBlKSA9PiB7XG4gICAgICAgICAgaW5wdXRFbGVtZW50LmFkZEV2ZW50TGlzdGVuZXIoZXZlbnRUeXBlLCAoZXZlbnQpID0+IHtcbiAgICAgICAgICAgIGV2ZW50LnByZXZlbnREZWZhdWx0KCk7XG4gICAgICAgICAgICBldmVudC5zdG9wUHJvcGFnYXRpb24oKTtcbiAgICAgICAgICAgIHRoaXMuaGFuZGxlSW5wdXRJbnRlcmFjdGlvbihldmVudCwgbm9kZSk7XG4gICAgICAgICAgfSwgdHJ1ZSk7XG4gICAgICAgIH0pO1xuXG4gICAgICAgIC8vIGZvciBQcmV2ZW50IGFueSB0eXBpbmcgb3IgaW50ZXJhY3Rpb25cbiAgICAgICAgaW5wdXRFbGVtZW50LmFkZEV2ZW50TGlzdGVuZXIoJ2tleXByZXNzJywgKGV2ZW50KSA9PiB7XG4gICAgICAgICAgZXZlbnQucHJldmVudERlZmF1bHQoKTtcbiAgICAgICAgICBldmVudC5zdG9wUHJvcGFnYXRpb24oKTtcbiAgICAgICAgfSwgdHJ1ZSk7XG5cbiAgICAgICAgaW5wdXRFbGVtZW50LnNldEF0dHJpYnV0ZSgncmVhZG9ubHknLCB0cnVlKTtcbiAgICAgICAgaW5wdXRFbGVtZW50LnNldEF0dHJpYnV0ZSgndGFiaW5kZXgnLCAnLTEnKTtcbiAgICAgIH1cbiAgICB9LFxuXG4gICAgaGFuZGxlSW5wdXRJbnRlcmFjdGlvbjogZnVuY3Rpb24gKGV2ZW50LCBub2RlKSB7XG4gICAgICAvLyBEb24ndCBvcGVuIG1vZGFsIGlmIGl0J3MgYWxyZWFhZHkgb3BlblxuICAgICAgaWYgKHRoaXMubW9kYWxPcGVuKSB7XG4gICAgICAgIHJldHVybjtcbiAgICAgIH1cblxuICAgICAgLy8gSW1tZWRpYXRlbHkgYmx1ciB0aGUgaW5wdXQgdG8gcHJldmVudCBmb2N1c1xuICAgICAgaWYgKGV2ZW50LnRhcmdldCkge1xuICAgICAgICBldmVudC50YXJnZXQuYmx1cigpO1xuICAgICAgfVxuXG4gICAgICAvLyBPcGVuIHRoZSBtb2RhbFxuICAgICAgdGhpcy5vcGVuTW9kYWwobm9kZSk7XG4gICAgfSxcblxuICAgIG9wZW5Nb2RhbDogZnVuY3Rpb24gKG5vZGUpIHtcbiAgICAgIHRoaXMubW9kYWxPcGVuID0gdHJ1ZTtcbiAgICAgIG5vZGUuZGlzYWJsZWQgPSB0cnVlO1xuXG4gICAgICB3aW5kb3cuZGlzcGF0Y2hFdmVudChuZXcgQ3VzdG9tRXZlbnQoJ29wZW4tZ2xvYmFsLXNlYXJjaC1tb2RhbCcsIHtcbiAgICAgICAgZGV0YWlsOiB7IGlkOiAnZ2xvYmFsLXNlYXJjaC1tb2RhbDo6cGx1Z2luJyB9LFxuICAgICAgICBidWJibGVzOiB0cnVlLFxuICAgICAgfSkpO1xuICAgIH0sXG5cbiAgICBsaXN0ZW5Gb3JNb2RhbENsb3NlOiBmdW5jdGlvbiAobm9kZSkge1xuICAgICAgd2luZG93LmFkZEV2ZW50TGlzdGVuZXIoJ21vZGFsLWNsb3NlZCcsIChldmVudCkgPT4ge1xuICAgICAgICBpZiAoZXZlbnQuZGV0YWlsPy5pZCA9PT0gJ2dsb2JhbC1zZWFyY2gtbW9kYWw6OnBsdWdpbicpIHtcbiAgICAgICAgICB0aGlzLm1vZGFsT3BlbiA9IGZhbHNlO1xuICAgICAgICAgIGNvbnN0IGlucHV0RWxlbWVudCA9IG5vZGUucXVlcnlTZWxlY3RvcihcImlucHV0W3R5cGU9c2VhcmNoXVwiKTtcbiAgICAgICAgICBpbnB1dEVsZW1lbnQuZGlzYWJsZWQgPSBmYWxzZTtcbiAgICAgICAgICBpbnB1dEVsZW1lbnQuc2V0QXR0cmlidXRlKCdyZWFkb25seScsIGZhbHNlKTtcbiAgICAgICAgICBpbnB1dEVsZW1lbnQuc2V0QXR0cmlidXRlKCd0YWJpbmRleCcsIDApO1xuICAgICAgICB9XG4gICAgICB9KTtcbiAgICB9XG4gIH07XG59Il0sCiAgIm1hcHBpbmdzIjogIjtBQUFlLFNBQVIsV0FBNEI7QUFDakMsU0FBTztBQUFBLElBQ0wsVUFBVTtBQUFBLElBQ1YsV0FBVztBQUFBLElBRVgsTUFBTSxXQUFZO0FBQ2hCLFlBQU0sZUFBZTtBQUNyQixZQUFNLE9BQU8sU0FBUyxjQUFjLFlBQVk7QUFFaEQsVUFBSSxNQUFNO0FBQ1IsYUFBSyxXQUFXO0FBQ2hCLGFBQUssb0JBQW9CLElBQUk7QUFDN0IsYUFBSyxvQkFBb0IsSUFBSTtBQUFBLE1BQy9CO0FBQUEsSUFDRjtBQUFBLElBRUEscUJBQXFCLFNBQVUsTUFBTTtBQUNuQyxZQUFNLGVBQWUsS0FBSyxjQUFjLG9CQUFvQjtBQUU1RCxVQUFJLGNBQWM7QUFFaEIsU0FBQyxTQUFTLFNBQVMsV0FBVyxPQUFPLEVBQUUsUUFBUSxDQUFDLGNBQWM7QUFDNUQsdUJBQWEsaUJBQWlCLFdBQVcsQ0FBQyxVQUFVO0FBQ2xELGtCQUFNLGVBQWU7QUFDckIsa0JBQU0sZ0JBQWdCO0FBQ3RCLGlCQUFLLHVCQUF1QixPQUFPLElBQUk7QUFBQSxVQUN6QyxHQUFHLElBQUk7QUFBQSxRQUNULENBQUM7QUFHRCxxQkFBYSxpQkFBaUIsWUFBWSxDQUFDLFVBQVU7QUFDbkQsZ0JBQU0sZUFBZTtBQUNyQixnQkFBTSxnQkFBZ0I7QUFBQSxRQUN4QixHQUFHLElBQUk7QUFFUCxxQkFBYSxhQUFhLFlBQVksSUFBSTtBQUMxQyxxQkFBYSxhQUFhLFlBQVksSUFBSTtBQUFBLE1BQzVDO0FBQUEsSUFDRjtBQUFBLElBRUEsd0JBQXdCLFNBQVUsT0FBTyxNQUFNO0FBRTdDLFVBQUksS0FBSyxXQUFXO0FBQ2xCO0FBQUEsTUFDRjtBQUdBLFVBQUksTUFBTSxRQUFRO0FBQ2hCLGNBQU0sT0FBTyxLQUFLO0FBQUEsTUFDcEI7QUFHQSxXQUFLLFVBQVUsSUFBSTtBQUFBLElBQ3JCO0FBQUEsSUFFQSxXQUFXLFNBQVUsTUFBTTtBQUN6QixXQUFLLFlBQVk7QUFDakIsV0FBSyxXQUFXO0FBRWhCLGFBQU8sY0FBYyxJQUFJLFlBQVksNEJBQTRCO0FBQUEsUUFDL0QsUUFBUSxFQUFFLElBQUksOEJBQThCO0FBQUEsUUFDNUMsU0FBUztBQUFBLE1BQ1gsQ0FBQyxDQUFDO0FBQUEsSUFDSjtBQUFBLElBRUEscUJBQXFCLFNBQVUsTUFBTTtBQUNuQyxhQUFPLGlCQUFpQixnQkFBZ0IsQ0FBQyxVQUFVO0FBQ2pELFlBQUksTUFBTSxRQUFRLE9BQU8sK0JBQStCO0FBQ3RELGVBQUssWUFBWTtBQUNqQixnQkFBTSxlQUFlLEtBQUssY0FBYyxvQkFBb0I7QUFDNUQsdUJBQWEsV0FBVztBQUN4Qix1QkFBYSxhQUFhLFlBQVksS0FBSztBQUMzQyx1QkFBYSxhQUFhLFlBQVksQ0FBQztBQUFBLFFBQ3pDO0FBQUEsTUFDRixDQUFDO0FBQUEsSUFDSDtBQUFBLEVBQ0Y7QUFDRjsiLAogICJuYW1lcyI6IFtdCn0K
