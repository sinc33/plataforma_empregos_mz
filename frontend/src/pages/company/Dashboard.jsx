import React from 'react';
import { Container, Paper, Box, Typography, Grid, Card, CardContent, Chip } from '@mui/material';
import { Helmet } from 'react-helmet-async';
import { TrendingUp, Work, People, Business } from '@mui/icons-material';

function CompanyDashboard() {
  // TODO: substituir por dados reais
  const metrics = {
    activeJobs: 6,
    totalApplications: 127,
    inProcess: 34,
    interviews: 12,
  };

  const recentJobs = [
    { id: 1, title: 'Desenvolvedor Full Stack', status: 'Ativa', applications: 18 },
    { id: 2, title: 'UI/UX Designer', status: 'Rascunho', applications: 0 },
    { id: 3, title: 'Gestor de Projetos', status: 'Ativa', applications: 9 },
  ];

  return (
    <>
      <Helmet>
        <title>Dashboard da Empresa</title>
      </Helmet>

      <Container maxWidth="lg" sx={{ py: 4 }}>
        {/* Cards de métricas */}
        <Grid container spacing={2}>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <Box>
                    <Typography variant="subtitle2" color="text.secondary">Vagas Ativas</Typography>
                    <Typography variant="h4" fontWeight="bold">{metrics.activeJobs}</Typography>
                  </Box>
                  <Work color="primary" />
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <Box>
                    <Typography variant="subtitle2" color="text.secondary">Candidaturas</Typography>
                    <Typography variant="h4" fontWeight="bold">{metrics.totalApplications}</Typography>
                  </Box>
                  <People color="primary" />
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <Box>
                    <Typography variant="subtitle2" color="text.secondary">Em Processo</Typography>
                    <Typography variant="h4" fontWeight="bold">{metrics.inProcess}</Typography>
                  </Box>
                  <TrendingUp color="primary" />
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <Box>
                    <Typography variant="subtitle2" color="text.secondary">Entrevistas</Typography>
                    <Typography variant="h4" fontWeight="bold">{metrics.interviews}</Typography>
                  </Box>
                  <Business color="primary" />
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>

        {/* Últimas vagas */}
        <Paper sx={{ p: 3, mt: 3 }}>
          <Typography variant="h6" fontWeight="bold" gutterBottom>
            Últimas Vagas
          </Typography>

          <Grid container spacing={2}>
            {recentJobs.map((job) => (
              <Grid item xs={12} md={4} key={job.id}>
                <Card>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight="bold">
                      {job.title}
                    </Typography>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 1 }}>
                      <Chip label={job.status} size="small" color={job.status === 'Ativa' ? 'success' : 'default'} />
                      <Typography variant="caption" color="text.secondary">
                        {job.applications} candidaturas
                      </Typography>
                    </Box>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Paper>
      </Container>
    </>
  );
}

export default CompanyDashboard;
