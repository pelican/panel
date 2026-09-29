// resources/js/search.js
function searchComponent({
  recentSearchesKey,
  favoriteSearchesKey,
  maxItemsAllowed,
  retainRecentIfFavorite
}) {
  return {
    search_history: [],
    favorite_items: [],
    init() {
      this.search_history = this.getLocalStorage(recentSearchesKey);
      this.favorite_items = this.getLocalStorage(favoriteSearchesKey);
      this.$watch(
        "search_history",
        (val) => this.setLocalStorage(recentSearchesKey, val)
      );
      this.$watch(
        "favorite_items",
        (val) => this.setLocalStorage(favoriteSearchesKey, val)
      );
    },
    getLocalStorage(key) {
      return JSON.parse(localStorage.getItem(key)) || [];
    },
    setLocalStorage(key, value) {
      localStorage.setItem(key, JSON.stringify(value));
    },
    updateList(list, newItem) {
      return [
        newItem,
        ...list.filter((el) => !(el.title === newItem.title && el.group === newItem.group))
      ].slice(0, maxItemsAllowed);
    },
    addToSearchHistory(searchItem, group, url) {
      const searchItemObject = { title: searchItem, group, url };
      this.search_history = this.updateList(
        this.search_history,
        searchItemObject
      );
    },
    deleteFromHistory(searchItem, group) {
      this.search_history = this.search_history.filter(
        (el) => !(el.title === searchItem && el.group === group)
      );
    },
    deleteAllHistory() {
      this.search_history = [];
    },
    addToFavorites(favItem, group, url) {
      if (!retainRecentIfFavorite) {
        this.deleteFromHistory(favItem, group);
      }
      const favItemObject = { title: favItem, group, url };
      this.favorite_items = this.updateList(
        this.favorite_items,
        favItemObject
      );
    },
    deleteFromFavorites(favItem, group) {
      this.favorite_items = this.favorite_items.filter(
        (el) => !(el.title === favItem && el.group === group)
      );
    },
    deleteAllFavorites() {
      this.favorite_items = [];
    }
  };
}
export {
  searchComponent as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsiLi4vcmVzb3VyY2VzL2pzL3NlYXJjaC5qcyJdLAogICJzb3VyY2VzQ29udGVudCI6IFsiZXhwb3J0IGRlZmF1bHQgZnVuY3Rpb24gc2VhcmNoQ29tcG9uZW50KHtcclxuICAgIHJlY2VudFNlYXJjaGVzS2V5LFxyXG4gICAgZmF2b3JpdGVTZWFyY2hlc0tleSxcclxuICAgIG1heEl0ZW1zQWxsb3dlZCxcclxuICAgIHJldGFpblJlY2VudElmRmF2b3JpdGVcclxufSkge1xyXG4gICAgcmV0dXJuIHtcclxuXHJcbiAgICAgICAgc2VhcmNoX2hpc3Rvcnk6IFtdLFxyXG4gICAgICAgIGZhdm9yaXRlX2l0ZW1zOiBbXSxcclxuXHJcbiAgICAgICAgaW5pdCgpIHtcclxuICAgICAgICAgICAgdGhpcy5zZWFyY2hfaGlzdG9yeSA9IHRoaXMuZ2V0TG9jYWxTdG9yYWdlKHJlY2VudFNlYXJjaGVzS2V5KTtcclxuICAgICAgICAgICAgdGhpcy5mYXZvcml0ZV9pdGVtcyA9IHRoaXMuZ2V0TG9jYWxTdG9yYWdlKGZhdm9yaXRlU2VhcmNoZXNLZXkpO1xyXG5cclxuICAgICAgICAgICAgdGhpcy4kd2F0Y2goXCJzZWFyY2hfaGlzdG9yeVwiLCAodmFsKSA9PlxyXG4gICAgICAgICAgICAgICAgdGhpcy5zZXRMb2NhbFN0b3JhZ2UocmVjZW50U2VhcmNoZXNLZXksIHZhbClcclxuICAgICAgICAgICAgKTtcclxuICAgICAgICAgICAgdGhpcy4kd2F0Y2goXCJmYXZvcml0ZV9pdGVtc1wiLCAodmFsKSA9PlxyXG4gICAgICAgICAgICAgICAgdGhpcy5zZXRMb2NhbFN0b3JhZ2UoZmF2b3JpdGVTZWFyY2hlc0tleSwgdmFsKVxyXG4gICAgICAgICAgICApO1xyXG4gICAgICAgIH0sXHJcblxyXG4gICAgICAgIGdldExvY2FsU3RvcmFnZShrZXkpIHtcclxuICAgICAgICAgICAgcmV0dXJuIEpTT04ucGFyc2UobG9jYWxTdG9yYWdlLmdldEl0ZW0oa2V5KSkgfHwgW107XHJcbiAgICAgICAgfSxcclxuXHJcbiAgICAgICAgc2V0TG9jYWxTdG9yYWdlKGtleSwgdmFsdWUpIHtcclxuICAgICAgICAgICAgbG9jYWxTdG9yYWdlLnNldEl0ZW0oa2V5LCBKU09OLnN0cmluZ2lmeSh2YWx1ZSkpO1xyXG4gICAgICAgIH0sXHJcblxyXG4gICAgICAgIHVwZGF0ZUxpc3QobGlzdCwgbmV3SXRlbSkge1xyXG4gICAgICAgICAgICByZXR1cm4gW1xyXG4gICAgICAgICAgICAgICAgbmV3SXRlbSxcclxuICAgICAgICAgICAgICAgIC4uLmxpc3QuZmlsdGVyKChlbCkgPT4gIShlbC50aXRsZSA9PT0gbmV3SXRlbS50aXRsZSAmJiBlbC5ncm91cCA9PT0gbmV3SXRlbS5ncm91cCkpLFxyXG4gICAgICAgICAgICBdLnNsaWNlKDAsIG1heEl0ZW1zQWxsb3dlZCk7XHJcbiAgICAgICAgfSxcclxuXHJcbiAgICAgICAgYWRkVG9TZWFyY2hIaXN0b3J5KHNlYXJjaEl0ZW0sIGdyb3VwLCB1cmwpIHtcclxuICAgICAgICAgICAgY29uc3Qgc2VhcmNoSXRlbU9iamVjdCA9IHsgdGl0bGU6IHNlYXJjaEl0ZW0sIGdyb3VwLCB1cmwgfTtcclxuICAgICAgICAgICAgdGhpcy5zZWFyY2hfaGlzdG9yeSA9IHRoaXMudXBkYXRlTGlzdChcclxuICAgICAgICAgICAgICAgIHRoaXMuc2VhcmNoX2hpc3RvcnksXHJcbiAgICAgICAgICAgICAgICBzZWFyY2hJdGVtT2JqZWN0XHJcbiAgICAgICAgICAgICk7XHJcbiAgICAgICAgfSxcclxuXHJcbiAgICAgICAgZGVsZXRlRnJvbUhpc3Rvcnkoc2VhcmNoSXRlbSwgZ3JvdXApIHtcclxuICAgICAgICAgICAgdGhpcy5zZWFyY2hfaGlzdG9yeSA9IHRoaXMuc2VhcmNoX2hpc3RvcnkuZmlsdGVyKFxyXG4gICAgICAgICAgICAgICAgKGVsKSA9PiAhKGVsLnRpdGxlID09PSBzZWFyY2hJdGVtICYmIGVsLmdyb3VwID09PSBncm91cClcclxuICAgICAgICAgICAgKTtcclxuICAgICAgICB9LFxyXG5cclxuICAgICAgICBkZWxldGVBbGxIaXN0b3J5KCkge1xyXG4gICAgICAgICAgICB0aGlzLnNlYXJjaF9oaXN0b3J5ID0gW107XHJcbiAgICAgICAgfSxcclxuXHJcbiAgICAgICAgYWRkVG9GYXZvcml0ZXMoZmF2SXRlbSwgZ3JvdXAsIHVybCkge1xyXG4gICAgICAgICAgICAvLyBoZXJlIGlmIHRoZSBpdGVtIG1hcmtlZCBhcyBmYXZvcml0ZXMgd2UgbWF5IG9wdGlvbmFsbHkgZGVsZXRlIGl0IGZyb20gcmVjZW50IHNlYXJjaHNcclxuICAgICAgICAgICAgaWYgKCFyZXRhaW5SZWNlbnRJZkZhdm9yaXRlKSB7XHJcbiAgICAgICAgICAgICAgICB0aGlzLmRlbGV0ZUZyb21IaXN0b3J5KGZhdkl0ZW0sIGdyb3VwKTtcclxuICAgICAgICAgICAgfVxyXG5cclxuICAgICAgICAgICAgY29uc3QgZmF2SXRlbU9iamVjdCA9IHsgdGl0bGU6IGZhdkl0ZW0sIGdyb3VwLCB1cmwgfTtcclxuICAgICAgICAgICAgdGhpcy5mYXZvcml0ZV9pdGVtcyA9IHRoaXMudXBkYXRlTGlzdChcclxuICAgICAgICAgICAgICAgIHRoaXMuZmF2b3JpdGVfaXRlbXMsXHJcbiAgICAgICAgICAgICAgICBmYXZJdGVtT2JqZWN0XHJcbiAgICAgICAgICAgICk7XHJcbiAgICAgICAgfSxcclxuXHJcbiAgICAgICAgZGVsZXRlRnJvbUZhdm9yaXRlcyhmYXZJdGVtLCBncm91cCkge1xyXG4gICAgICAgICAgICB0aGlzLmZhdm9yaXRlX2l0ZW1zID0gdGhpcy5mYXZvcml0ZV9pdGVtcy5maWx0ZXIoXHJcbiAgICAgICAgICAgICAgICAoZWwpID0+ICEoZWwudGl0bGUgPT09IGZhdkl0ZW0gJiYgZWwuZ3JvdXAgPT09IGdyb3VwKVxyXG4gICAgICAgICAgICApO1xyXG4gICAgICAgIH0sXHJcblxyXG4gICAgICAgIGRlbGV0ZUFsbEZhdm9yaXRlcygpIHtcclxuICAgICAgICAg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
