// Store module for i18n translations
export default {
  state: {
    translationProgress: 0,
    translationStatus: 'idle', // idle, processing, completed, failed
    translationMessage: ''
  },
  
  mutations: {
    setTranslationProgress(state, progress) {
      state.translationProgress = progress
    },
    
    setTranslationStatus(state, status) {
      state.translationStatus = status
    },
    
    setTranslationMessage(state, message) {
      state.translationMessage = message
    },
    
    resetTranslationState(state) {
      state.translationProgress = 0
      state.translationStatus = 'idle'
      state.translationMessage = ''
    }
  }
} 