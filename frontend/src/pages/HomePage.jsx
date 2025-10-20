import React, { useState } from 'react';
import {
  Box,
  Container,
  Typography,
  Button,
  Grid,
  Card,
  CardContent,
  TextField,
  InputAdornment,
  Chip,
  Avatar,
  Paper,
  IconButton
} from '@mui/material';
import {
  Search as SearchIcon,
  LocationOn,
  Business,
  ArrowForward
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../contexts/AuthContext';

function HomePage() {
  const navigate = useNavigate();
  const { isAuthenticated, userType } = useAuth();
  const [searchTerm, setSearchTerm] = useState('');

  const handleSearch = () => {
    if (searchTerm.trim()) {
      navigate(`/vagas?search=${encodeURIComponent(searchTerm)}`);
    } else {
      navigate('/vagas');
    }
  };

  return (
    <>
      <Helmet>
        <title>Plataforma de Empregos - Moçambique | Encontre sua oportunidade</title>
        <meta name="description" content="A maior plataforma de empregos de Moçambique. Conectamos talentos com oportunidades." />
      </Helmet>

      {/* Hero Section */}
      <Box
        sx={{
          background: 'linear-gradient(135deg, #1976d2 0%, #42a5f5 100%)',
          color: 'white',
          py: { xs: 6, md: 10 }
        }}
      >
        <Container maxWidth="lg">
          <Grid container spacing={4} alignItems="center">
            <Grid item xs={12} md={8}>
              <Typography variant="h2" component="h1" gutterBottom sx={{ fontWeight: 'bold' }}>
                Encontre sua próxima{' '}
                <Box component="span" sx={{ color: '#f57c00' }}>
                  oportunidade
                </Box>
              </Typography>
              
              <Typography variant="h5" sx={{ mb: 4, opacity: 0.9 }}>
                Conectamos os melhores talentos às melhores empresas em Moçambique
              </Typography>

              <Paper sx={{ p: 1, display: 'flex', alignItems: 'center', mb: 4 }}>
                <TextField
                  fullWidth
                  placeholder="Pesquisar por cargo, empresa ou palavra-chave..."
                  variant="standard"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  InputProps={{
                    disableUnderline: true,
                    startAdornment: (
                      <InputAdornment position="start">
                        <SearchIcon color="action" />
                      </InputAdornment>
                    )
                  }}
                />
                <Button variant="contained" onClick={handleSearch} sx={{ ml: 1 }}>
                  Pesquisar
                </Button>
              </Paper>

              <Box sx={{ display: 'flex', gap: 2 }}>
                {!isAuthenticated ? (
                  <>
                    <Button
                      variant="contained"
                      size="large"
                      onClick={() => navigate('/register')}
                      sx={{ backgroundColor: '#f57c00' }}
                    >
                      Começar Agora
                    </Button>
                    <Button
                      variant="outlined"
                      size="large"
                      onClick={() => navigate('/vagas')}
                      sx={{ borderColor: 'white', color: 'white' }}
                    >
                      Ver Vagas
                    </Button>
                  </>
                ) : (
                  <Button
                    variant="contained"
                    size="large"
                    onClick={() => navigate(`/${userType}/dashboard`)}
                    sx={{ backgroundColor: '#f57c00' }}
                  >
                    Ir para Dashboard
                  </Button>
                )}
              </Box>
            </Grid>
          </Grid>
        </Container>
      </Box>

      {/* CTA para Empresas */}
      {(!isAuthenticated || userType !== 'company') && (
        <Box sx={{ py: 6 }}>
          <Container maxWidth="md">
            <Paper sx={{ p: 4, textAlign: 'center', background: 'linear-gradient(135deg, #f57c00 0%, #ff9800 100%)', color: 'white' }}>
              <Business sx={{ fontSize: 48, mb: 2 }} />
              <Typography variant="h4" gutterBottom fontWeight="bold">
                É uma empresa?
              </Typography>
              <Typography variant="h6" sx={{ mb: 3 }}>
                Encontre os melhores talentos para sua equipa
              </Typography>
              <Button
                variant="contained"
                size="large"
                onClick={() => navigate('/register?type=company')}
                sx={{ backgroundColor: 'white', color: 'primary.main' }}
              >
                Publicar Vaga Grátis
              </Button>
            </Paper>
          </Container>
        </Box>
      )}
    </>
  );
}

export default HomePage;