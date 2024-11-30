import axios from 'axios'

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
    async fetchEventTypes({ commit }) {
      commit('SET_LOADING', true)
      commit('SET_ERROR', null)
      try {
        const response = await axios.get('/api/getAllEventTypes')
        commit('SET_EVENT_TYPES', response.data)
      } catch (error) {
        console.error('Error fetching event types:', error)
        commit('SET_ERROR', 'Failed to fetch event types')
      } finally {
        commit('SET_LOADING', false)
      }
    }
  },

  getters: {
    getAllEventTypes: (state) => state.eventTypes,
    getEventTypeById: (state) => (id) => state.eventTypes.find(et => et.id === id),
    isLoading: (state) => state.loading,
    hasError: (state) => state.error !== null
  }
}