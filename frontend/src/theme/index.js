import { createTheme } from '@mui/material/styles';
import { ptPT } from '@mui/material/locale';

// Cores baseadas na identidade moçambicana
const colors = {
  primary: {
    main: '#1976d2', // Azul profissional
    light: '#42a5f5',
    dark: '#1565c0',
    contrastText: '#ffffff'
  },
  secondary: {
    main: '#f57c00', // Laranja vibrante (inspirado na bandeira)
    light: '#ffb74d',
    dark: '#e65100',
    contrastText: '#ffffff'
  },
  success: {
    main: '#388e3c', // Verde (inspirado na bandeira)
    light: '#66bb6a',
    dark: '#2e7d32'
  },
  warning: {
    main: '#ffa000',
    light: '#ffb74d',
    dark: '#f57c00'
  },
  error: {
    main: '#d32f2f', // Vermelho (inspirado na bandeira)
    light: '#ef5350',
    dark: '#c62828'
  },
  grey: {
    50: '#fafafa',
    100: '#f5f5f5',
    200: '#eeeeee',
    300: '#e0e0e0',
    400: '#bdbdbd',
    500: '#9e9e9e',
    600: '#757575',
    700: '#616161',
    800: '#424242',
    900: '#212121'
  }
};

// Configuração de tipografia
const typography = {
  fontFamily: [
    'Roboto',
    '-apple-system',
    'BlinkMacSystemFont',
    '"Segoe UI"',
    '"Helvetica Neue"',
    'Arial',
    'sans-serif'
  ].join(','),
  h1: {
    fontSize: '2.5rem',
    fontWeight: 600,
    lineHeight: 1.2
  },
  h2: {
    fontSize: '2rem',
    fontWeight: 600,
    lineHeight: 1.3
  },
  h3: {
    fontSize: '1.75rem',
    fontWeight: 600,
    lineHeight: 1.4
  },
  h4: {
    fontSize: '1.5rem',
    fontWeight: 600,
    lineHeight: 1.4
  },
  h5: {
    fontSize: '1.25rem',
    fontWeight: 600,
    lineHeight: 1.5
  },
  h6: {
    fontSize: '1rem',
    fontWeight: 600,
    lineHeight: 1.6
  },
  body1: {
    fontSize: '1rem',
    lineHeight: 1.5
  },
  body2: {
    fontSize: '0.875rem',
    lineHeight: 1.43
  },
  button: {
    textTransform: 'none', // Remove uppercase automático
    fontWeight: 500
  }
};

// Configuração de componentes
const components = {
  MuiButton: {
    styleOverrides: {
      root: {
        borderRadius: 8,
        textTransform: 'none',
        fontWeight: 500,
        padding: '8px 22px'
      },
      contained: {
        boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
        '&:hover': {
          boxShadow: '0 4px 8px rgba(0,0,0,0.15)'
        }
      }
    }
  },
  MuiCard: {
    styleOverrides: {
      root: {
        borderRadius: 12,
        boxShadow: '0 2px 12px rgba(0,0,0,0.08)'
      }
    }
  },
  MuiTextField: {
    styleOverrides: {
      root: {
        '& .MuiOutlinedInput-root': {
          borderRadius: 8
        }
      }
    }
  },
  MuiChip: {
    styleOverrides: {
      root: {
        borderRadius: 6
      }
    }
  },
  MuiAppBar: {
    styleOverrides: {
      root: {
        boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
      }
    }
  }
};

// Criar tema
const theme = createTheme(
  {
    palette: {
      mode: 'light',
      ...colors,
      background: {
        default: '#fafafa',
        paper: '#ffffff'
      }
    },
    typography,
    components,
    shape: {
      borderRadius: 8
    },
    spacing: 8
  },
  ptPT // Localização para português
);

// Tema escuro (opcional para futuras implementações)
export const darkTheme = createTheme(
  {
    palette: {
      mode: 'dark',
      ...colors,
      background: {
        default: '#121212',
        paper: '#1e1e1e'
      }
    },
    typography,
    components,
    shape: {
      borderRadius: 8
    },
    spacing: 8
  },
  ptPT
);

export default theme;
