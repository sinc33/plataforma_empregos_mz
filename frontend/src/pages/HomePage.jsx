import React, { useState, useEffect } from 'react';
import {
  Box,
  Container,
  Typography,
  Button,
  TextField,
  Grid,
  Card,
  CardContent,
  CardActions,
  Chip,
  InputAdornment,
  Paper,
  Stack,
  Avatar,
  Divider,
  Rating
} from '@mui/material';
import {
  Search as SearchIcon,
  LocationOn as LocationIcon,
  Business as BusinessIcon,
  People as PeopleIcon,
  TrendingUp as TrendingUpIcon,
  Work as WorkIcon,
  Star as StarIcon
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import axiosConfig from '../services/axiosConfig';

function HomePage() {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const [searchTerm, setSearchTerm] = useState('');
  const [featuredJobs, setFeaturedJobs] = useState([]);
  const [stats, setStats] = useState({
    totalJobs: 0,
    totalCompanies: 0,
    totalCandidates: 0,
    placementRate: 0
  });
  const [testimonials] = useState([
    {
      name: 'Ana Massinga',
      role: 'Desenvolvedora de Software',
      company: 'TechMoz',
      content: 'Encontrei minha vaga ideal através da plataforma. O processo foi rápido e transparente.',
      rating: 5,
      avatar: 'A'
    },
    {
      name: 'Carlos Nhampossa',
      role: 'Gerente de RH',
      company: 'BIM',
      content: 'Excelente ferramenta para encontrar talentos qualificados em Moçambique.',
      rating: 5,
      avatar: 'C'
    },
    {
      name: 'Maria Santos',
      role: 'Marketing Manager',
      company: 'Vodacom',
      content: 'Interface intuitiva e candidatos de qualidade. Recomendo a todas as empresas.',
      rating: 5,
      avatar: 'M'
    }
  ]);

  // Categorias populares
  const [categories] = useState([
    { name: 'Tecnologia', count: 245, icon: '💻' },
    { name: 'Saúde', count: 189, icon: '🏥' },
    { name: 'Educação', count: 156, icon: '🎓' },
    { name: 'Finanças', count: 134, icon: '💰' },
    { name: 'Engenharia', count: 112, icon: '⚙️' },
    { name: 'Marketing', count: 98, icon: '📊' },
    { name: 'Vendas', count: 87, icon: '🤝' },
    { name: 'Recursos Humanos', count: 65, icon: '👥' }
  ]);

  useEffect(() => {
    loadFeaturedJobs();
    loadStats();
  }, []);

  const loadFeaturedJobs = async () => {
    try {
      const response = await axiosConfig.get('/jobs/featured');
      if (response.data.success) {
        setFeaturedJobs(response.data.jobs);
      }
    } catch (error) {
      console.error('Erro ao carregar vagas em destaque:', error);
      // Mock data para desenvolvimento
      setFeaturedJobs([
        {
          id: 1,
          title: 'Desenvolvedor Full Stack',
          company: 'TechMoz',
          location: 'Maputo',
          salary: '45.000 - 65.000 MT',
          type: 'Tempo Integral',
          posted_date: '2025-10-18',
          logo: null,
          tags: ['React', 'Node.js', 'PHP']
        },
        {
          id: 2,
          title: 'Analista de Marketing Digital',
          company: 'Vodacom Moçambique',
          location: 'Maputo',
          salary: '35.000 - 50.000 MT',
          type: 'Tempo Integral',
          posted_date: '2025-10-17',
          logo: null,
          tags: ['Google Ads', 'Facebook Ads', 'Analytics']
        },
        {
          id: 3,
          title: 'Gestor de Projectos',
          company: 'BIM',
          location: 'Beira',
          salary: '40.000 - 60.000 MT',
          type: 'Tempo Integral',
          posted_date: '2025-10-16',
          logo: null,
          tags: ['PMP', 'Scrum', 'Agile']
        }
      ]);
    }
  };

  const loadStats = async () => {
    try {
      const response = await axiosConfig.get('/stats');
      if (response.data.success) {
        setStats(response.data.stats);
      }
    } catch (error) {
      console.error('Erro ao carregar estatísticas:', error);
      // Mock data para desenvolvimento
      setStats({
        totalJobs: 1247,
        totalCompanies: 342,
        totalCandidates: 5689,
        placementRate: 78
      });
    }
  };

  const handleSearch = () => {
    if (searchTerm.trim()) {
      navigate(`/vagas?search=${encodeURIComponent(searchTerm)}`);
    } else {
      navigate('/vagas');
    }
  };

  const formatSalary = (salary) => {
    if (!salary) return 'Salário a combinar';
    return salary;
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const diffTime = Math.abs(today - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Ontem';
    if (diffDays < 7) return `Há ${diffDays} dias`;
    return date.toLocaleDateString('pt-MZ');
  };

  return (
    <Box>
      {/* Hero Section */}
      <Box
        sx={{
          background: 'linear-gradient(135deg, #1976d2 0%, #1565c0 100%)',
          color: 'white',
          py: { xs: 6, md: 10 },
          position: 'relative',
          overflow: 'hidden'
        }}
      >
        {/* Background decorativo */}
        <Box
          sx={{
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'url("data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.05"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
            opacity: 0.1
          }}
        />
        
        <Container maxWidth="lg" sx={{ position: 'relative', zIndex: 1 }}>
          <Grid container spacing={4} alignItems="center">
            <Grid item xs={12} md={6}>
              <Typography 
                variant="h2" 
                component="h1" 
                fontWeight="bold" 
                gutterBottom
                sx={{ fontSize: { xs: '2rem', md: '3rem' } }}
              >
                Encontre sua oportunidade ideal em Moçambique
              </Typography>
              <Typography 
                variant="h6" 
                sx={{ mb: 4, opacity: 0.9, fontSize: { xs: '1rem', md: '1.25rem' } }}
              >
                A maior plataforma de empregos do país. Conectamos talentos com as melhores empresas.
              </Typography>
              
              {/* Barra de pesquisa */}
              <Paper 
                elevation={3} 
                sx={{ 
                  p: 1, 
                  display: 'flex', 
                  backgroundColor: 'white',
                  borderRadius: 3
                }}
              >
                <TextField
                  fullWidth
                  placeholder="Pesquisar por cargo, empresa ou competência..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                  variant="standard"
                  InputProps={{
                    disableUnderline: true,
                    startAdornment: (
                      <InputAdornment position="start">
                        <SearchIcon color="action" />
                      </InputAdornment>
                    ),
                    sx: { px: 2, py: 1 }
                  }}
                />
                <Button 
                  variant="contained" 
                  onClick={handleSearch}
                  sx={{ ml: 1, px: 4, borderRadius: 2 }}
                >
                  Pesquisar
                </Button>
              </Paper>
              
              {/* CTAs */}
              {!isAuthenticated && (
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
                  <Button 
                    variant="contained" 
                    size="large"
                    onClick={() => navigate('/register?type=candidate')}
                    sx={{ 
                      bgcolor: 'rgba(255,255,255,0.2)', 
                      '&:hover': { bgcolor: 'rgba(255,255,255,0.3)' },
                      backdropFilter: 'blur(10px)'
                    }}
                  >
                    Sou Candidato
                  </Button>
                  <Button 
                    variant="outlined" 
                    size="large"
                    onClick={() => navigate('/register?type=company')}
                    sx={{ 
                      color: 'white',
                      borderColor: 'rgba(255,255,255,0.5)',
                      '&:hover': { 
                        borderColor: 'white',
                        bgcolor: 'rgba(255,255,255,0.1)'
                      }
                    }}
                  >
                    Sou Empresa
                  </Button>
                </Stack>
              )}
            </Grid>
            
            <Grid item xs={12} md={6}>
              {/* Estatísticas */}
              <Grid container spacing={2}>
                <Grid item xs={6}>
                  <Paper 
                    elevation={3} 
                    sx={{ 
                      p: 3, 
                      textAlign: 'center',
                      backgroundColor: 'rgba(255,255,255,0.95)',
                      color: 'text.primary'
                    }}
                  >
                    <WorkIcon sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
                    <Typography variant="h4" fontWeight="bold" color="primary">
                      {stats.totalJobs.toLocaleString()}
                    </Typography>
                    <Typography variant="body2">Vagas Activas</Typography>
                  </Paper>
                </Grid>
                <Grid item xs={6}>
                  <Paper 
                    elevation={3} 
                    sx={{ 
                      p: 3, 
                      textAlign: 'center',
                      backgroundColor: 'rgba(255,255,255,0.95)',
                      color: 'text.primary'
                    }}
                  >
                    <BusinessIcon sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
                    <Typography variant="h4" fontWeight="bold" color="primary">
                      {stats.totalCompanies.toLocaleString()}
                    </Typography>
                    <Typography variant="body2">Empresas</Typography>
                  </Paper>
                </Grid>
                <Grid item xs={6}>
                  <Paper 
                    elevation={3} 
                    sx={{ 
                      p: 3, 
                      textAlign: 'center',
                      backgroundColor: 'rgba(255,255,255,0.95)',
                      color: 'text.primary'
                    }}
                  >
                    <PeopleIcon sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
                    <Typography variant="h4" fontWeight="bold" color="primary">
                      {stats.totalCandidates.toLocaleString()}
                    </Typography>
                    <Typography variant="body2">Candidatos</Typography>
                  </Paper>
                </Grid>
                <Grid item xs={6}>
                  <Paper 
                    elevation={3} 
                    sx={{ 
                      p: 3, 
                      textAlign: 'center',
                      backgroundColor: 'rgba(255,255,255,0.95)',
                      color: 'text.primary'
                    }}
                  >
                    <TrendingUpIcon sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
                    <Typography variant="h4" fontWeight="bold" color="primary">
                      {stats.placementRate}%
                    </Typography>
                    <Typography variant="body2">Taxa de Colocação</Typography>
                  </Paper>
                </Grid>
              </Grid>
            </Grid>
          </Grid>
        </Container>
      </Box>

      <Container maxWidth="lg" sx={{ py: 8 }}>
        {/* Categorias Populares */}
        <Box sx={{ mb: 8 }}>
          <Typography variant="h4" component="h2" fontWeight="bold" textAlign="center" gutterBottom>
            Categorias Populares
          </Typography>
          <Typography variant="h6" textAlign="center" color="text.secondary" sx={{ mb: 4 }}>
            Explore oportunidades nas áreas mais procuradas
          </Typography>
          
          <Grid container spacing={2}>
            {categories.map((category, index) => (
              <Grid item xs={6} sm={4} md={3} key={index}>
                <Card 
                  sx={{ 
                    cursor: 'pointer',
                    transition: 'transform 0.2s, box-shadow 0.2s',
                    '&:hover': {
                      transform: 'translateY(-4px)',
                      boxShadow: 4
                    }
                  }}
                  onClick={() => navigate(`/vagas?category=${encodeURIComponent(category.name)}`)}
                >
                  <CardContent sx={{ textAlign: 'center', py: 3 }}>
                    <Typography variant="h4" sx={{ mb: 1 }}>{category.icon}</Typography>
                    <Typography variant="h6" fontWeight="bold" gutterBottom>
                      {category.name}
                    </Typography>
                    <Chip 
                      label={`${category.count} vagas`} 
                      size="small" 
                      color="primary" 
                      variant="outlined"
                    />
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Box>

        {/* Vagas em Destaque */}
        <Box sx={{ mb: 8 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 4 }}>
            <Box>
              <Typography variant="h4" component="h2" fontWeight="bold" gutterBottom>
                Vagas em Destaque
              </Typography>
              <Typography variant="h6" color="text.secondary">
                As melhores oportunidades para você
              </Typography>
            </Box>
            <Button 
              variant="outlined" 
              onClick={() => navigate('/vagas')}
              sx={{ display: { xs: 'none', sm: 'flex' } }}
            >
              Ver Todas
            </Button>
          </Box>
          
          <Grid container spacing={3}>
            {featuredJobs.map((job) => (
              <Grid item xs={12} md={6} lg={4} key={job.id}>
                <Card 
                  sx={{ 
                    height: '100%',
                    cursor: 'pointer',
                    transition: 'transform 0.2s, box-shadow 0.2s',
                    '&:hover': {
                      transform: 'translateY(-4px)',
                      boxShadow: 4
                    }
                  }}
                  onClick={() => navigate(`/vagas/${job.id}`)}
                >
                  <CardContent>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', mb: 2 }}>
                      <Box sx={{ flex: 1 }}>
                        <Typography variant="h6" fontWeight="bold" gutterBottom>
                          {job.title}
                        </Typography>
                        <Typography variant="body1" color="primary" fontWeight="medium" gutterBottom>
                          {job.company}
                        </Typography>
                      </Box>
                      <Avatar 
                        src={job.logo} 
                        sx={{ width: 48, height: 48, bgcolor: 'primary.main' }}
                      >
                        {job.company.charAt(0)}
                      </Avatar>
                    </Box>
                    
                    <Stack spacing={1} sx={{ mb: 2 }}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <LocationIcon fontSize="small" color="action" />
                        <Typography variant="body2" color="text.secondary">
                          {job.location}
                        </Typography>
                      </Box>
                      
                      <Typography variant="body2" color="text.primary" fontWeight="medium">
                        {formatSalary(job.salary)}
                      </Typography>
                      
                      <Typography variant="body2" color="text.secondary">
                        {job.type} • {formatDate(job.posted_date)}
                      </Typography>
                    </Stack>
                    
                    {job.tags && job.tags.length > 0 && (
                      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
                        {job.tags.slice(0, 3).map((tag, index) => (
                          <Chip 
                            key={index}
                            label={tag} 
                            size="small" 
                            variant="outlined"
                            sx={{ fontSize: '0.75rem' }}
                          />
                        ))}
                        {job.tags.length > 3 && (
                          <Chip 
                            label={`+${job.tags.length - 3}`} 
                            size="small" 
                            variant="outlined"
                            sx={{ fontSize: '0.75rem' }}
                          />
                        )}
                      </Stack>
                    )}
                  </CardContent>
                  
                  <CardActions>
                    <Button 
                      fullWidth 
                      variant="outlined"
                      onClick={(e) => {
                        e.stopPropagation();
                        navigate(`/vagas/${job.id}`);
                      }}
                    >
                      Ver Detalhes
                    </Button>
                  </CardActions>
                </Card>
              </Grid>
            ))}
          </Grid>
          
          <Box sx={{ textAlign: 'center', mt: 4, display: { sm: 'none' } }}>
            <Button 
              variant="contained" 
              onClick={() => navigate('/vagas')}
              size="large"
            >
              Ver Todas as Vagas
            </Button>
          </Box>
        </Box>

        {/* Testemunhos */}
        <Box sx={{ mb: 8 }}>
          <Typography variant="h4" component="h2" fontWeight="bold" textAlign="center" gutterBottom>
            O que dizem nossos utilizadores
          </Typography>
          <Typography variant="h6" textAlign="center" color="text.secondary" sx={{ mb: 6 }}>
            Histórias de sucesso de candidatos e empresas
          </Typography>
          
          <Grid container spacing={4}>
            {testimonials.map((testimonial, index) => (
              <Grid item xs={12} md={4} key={index}>
                <Card 
                  sx={{ 
                    height: '100%',
                    p: 2
                  }}
                >
                  <CardContent>
                    <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                      <Avatar sx={{ bgcolor: 'primary.main', mr: 2 }}>
                        {testimonial.avatar}
                      </Avatar>
                      <Box>
                        <Typography variant="h6" fontWeight="bold">
                          {testimonial.name}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                          {testimonial.role} • {testimonial.company}
                        </Typography>
                      </Box>
                    </Box>
                    
                    <Rating 
                      value={testimonial.rating} 
                      readOnly 
                      size="small" 
                      sx={{ mb: 2 }}
                    />
                    
                    <Typography variant="body1" sx={{ fontStyle: 'italic' }}>
                      "{testimonial.content}"
                    </Typography>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Box>

        {/* CTA Final */}
        {!isAuthenticated && (
          <Paper 
            elevation={2} 
            sx={{ 
              p: 6, 
              textAlign: 'center',
              background: 'linear-gradient(135deg, #f5f5f5 0%, #e8f5e8 100%)'
            }}
          >
            <Typography variant="h4" fontWeight="bold" gutterBottom>
              Pronto para dar o próximo passo?
            </Typography>
            <Typography variant="h6" color="text.secondary" sx={{ mb: 4 }}>
              Junte-se a milhares de profissionais e empresas que já encontraram sucesso na nossa plataforma
            </Typography>
            <Stack 
              direction={{ xs: 'column', sm: 'row' }} 
              spacing={3} 
              justifyContent="center"
            >
              <Button 
                variant="contained" 
                size="large"
                onClick={() => navigate('/register?type=candidate')}
                sx={{ px: 4, py: 1.5 }}
              >
                Registar como Candidato
              </Button>
              <Button 
                variant="outlined" 
                size="large"
                onClick={() => navigate('/register?type=company')}
                sx={{ px: 4, py: 1.5 }}
              >
                Registar como Empresa
              </Button>
            </Stack>
          </Paper>
        )}
      </Container>
    </Box>
  );
}

export default HomePage;