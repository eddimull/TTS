export default {
  namespaced: true,
  
  state: () => ({
    eventTypes: [],
    loading: false,
    error: null
  }),
  
  mutations: {
    SET_EVENT_TYPES(state, eventTypes) {
      state.eventTypes = eventTypes
    },
    SET_LOADING(state, isLoading) {
      state.loading = isLoading
    },
    SET_ERROR(state, error) {
      state.error = error
    }
  },
  
  actions: {
    // Initialize from Inertia shared data
    initializeEventTypes({ commit }, eventTypes) {
      commit('SET_EVENT_TYPES', eventTypes || [])
    }
  },

  getters: {
    getAllEventTypes: (state) => state.eventTypes,
    getEventTypeById: (state) => (id) => state.eventTypes.find(et => et.id === id),
    isLoading: (state) => state.loading,
    hasError: (state) => state.error !== null
  }
}